# PLAN.md — **Plugsent** · Open-Source WordPress Fleet Management Platform

**Plugsent**: a self-hosted, open-source alternative to WP Umbrella / ManageWP. Connect
WordPress sites, manage plugins/themes/updates, scan for vulnerabilities, monitor uptime,
organize sites into projects, assign team members, and expose everything to coding agents over
MCP — with AI built in and an API-first core that future mobile clients can build on.

**Stack decision: Laravel (chosen — team knows Laravel).** See §4.

---

## 1. Product shape: three components

```
┌─────────────────────────────────────────────────────────────────┐
│              PLUGSENT CONTROL PLANE (self-hosted)               │
│  Laravel app: Filament dashboard · REST /api/v1 · scheduler ·   │
│  uptime checker · vulnerability cache · multi-workspace RBAC    │
│  ┌────────────────┐  ┌──────────────────────────────────┐       │
│  │  MCP gateway   │  │  AI layer (BYO LLM / Ollama)     │       │
│  │  (laravel/mcp) │  │  (uses the SAME MCP tools)       │       │
│  └────────────────┘  └──────────────────────────────────┘       │
└──────────────▲──────────────────────────────────▲───────────────┘
               │ outbound HTTPS (poll/execute)    │ tools (scoped tokens)
   ┌───────────┴───────────┐          ┌───────────┴────────────┐
   │  WP CONNECTOR PLUGIN  │          │  Coding agents         │
   │  (PHP, on each site)  │          │  Claude Code, Cursor,  │
   │  inventory · updates  │          │  CI bots, (mobile app  │
   │  errors · heartbeats  │          │  via /api/v1 later)    │
   └───────────────────────┘          └────────────────────────┘
```

**Key architectural decision: sites call the platform (outbound), never the reverse.**
Most managed WP sites sit behind firewalls, HTTP auth, or staging auth. WP Umbrella and
ManageWP both use outbound connections for this reason. The platform queues commands; the
connector pulls and executes them.

Prior art worth studying (not cloning): **MainWP** (the main open-source option today, but it's
a WP-plugin dashboard rather than a standalone modern app — validates demand and shows gaps),
**WP-CLI** (the connector will reuse many of its code patterns), **InfiniteWP** (closed).

---

## 2. Database: PostgreSQL

**Recommendation: PostgreSQL 16+** (and nothing else for v1).

- Your data is deeply relational (orgs → projects → sites → plugin inventories → updates →
  events) — a relational model with real foreign keys is the right fit.
- **JSONB** solves the schema-flexibility problem: a site's plugin/theme inventory, PHP error
  payloads, and plugin changelogs are heterogeneous; store them as JSONB columns (Eloquent
  casts make these painless) alongside relational core tables instead of maintaining dozens of
  brittle tables.
- Self-hosting story is one dependency: **app + Postgres**. Avoid Redis as a hard dependency —
  Laravel's **database queue driver** + `schedule:work` covers jobs and cron for small/medium
  fleets. One container fewer makes the Docker Compose install trivially easy, which matters
  enormously for an OSS project's adoption. Add Horizon + Redis only when scale demands it.
- Row-level security is available later if you ever want DB-enforced tenant isolation.
- Full-text search (site/plugin names, error messages) is built in.

### Core schema sketch

```
users
workspaces(slug, name, owner_id)                  -- ONE created per signup; Filament tenants
workspace_members(workspace_id, user_id, role)    -- invited team members
projects, project_members(role)                   -- projects live inside a workspace
sites(project_id, url, php_version, wp_version, status, last_seen, tags jsonb)
site_credentials(site_id, site_key, hashed_site_secret, scopes)
commands(site_id, type, payload jsonb, status, nonce, expires_at, result jsonb, requested_by)
plugin_inventory(site_id, name, version, update_available, active, ...jsonb)
updates(id, site_id, slug, from→to, status, health_check_result, rolled_back bool)
uptime_checks(site_id, ts, code, latency_ms, region)
incidents(site_id, started_at, resolved_at, channel)
vulns_cache(slug, affected_versions jsonb, cve, severity, source, fetched_at)
php_errors(site_id, ts, message, file, line, count)   -- deduped error stream
api_tokens(name, hashed_token, scopes[], project_ids[])  -- Sanctum tokens: MCP, agents, mobile
support_access_requests(workspace_id, requested_by, reason, scope, status,
                        approved_by, granted_until)  -- consent-gated support sessions
audit_log(actor, actor_type: user|agent|mobile|support|system, action,
          on_behalf_of_workspace, target_site, payload, ts)
notification_channels(type: email|slack|discord|ntfy|push|webhook, config jsonb)
```

---

## 3. The connector (WordPress plugin)

### Pairing: how the keys get to the site (one-time handshake)

Three credentials play three different roles — don't conflate them:

| Credential | Role | Lifetime |
|---|---|---|
| **Pairing code** (`PLSG-XXXX-XXXX`) | One-time proof that the site owner authorized this connection | 15 min, single-use |
| **Site key** (`pk_…`) | Public identity of the site — tells the platform *which* site is calling | Until revoked |
| **Site secret** (256-bit random) | The actual credential — used to HMAC-sign every request | Until rotated/revoked |

Flow:

1. **Dashboard → "Connect a site"** → paste the site URL. Plugsent creates a single-use
   pairing code bound to the target project + URL, expiring in 15 minutes.
2. **On the WP site**: install the Plugsent Connector plugin (wordpress.org, zip upload, or a
   deep link `site.com/wp-admin/admin.php?page=plugsent&pair=PLSG-…`), then paste the code in
   Settings → Plugsent. Headless/bulk alternative: `wp plugsent pair PLSG-XXXX-XXXX`.
3. **Plugin → platform**: `POST /api/v1/connector/pair` with the code + site URL + WP/PHP
   versions + capability list. HTTPS authenticates the platform; the pairing code
   authenticates the site's owner's intent.
4. **Platform** validates the code (unused, unexpired, URL matches), creates the `sites` row,
   generates `site_key` + `site_secret`, and returns **both in this single response** (over
   TLS — the secret never travels again in plaintext form).
5. **Plugin** stores the secret encrypted (sodium seal using the site's own auth salts) in the
   options table, then sends its **first signed heartbeat** — the dashboard card flips to
   "Connected".

What the connector deliberately does **not** ask for: your WP admin password. The plugin acts
inside WordPress with its own capabilities; no stored admin credentials anywhere. Revoke from
the dashboard and the site goes dark instantly; "Rotate secret" delivers a new one through the
already-signed channel (`credentials.rotate` command), so rotation never breaks the connection.

### Communication model

**Outbound command-pull queue.** On pairing, the platform issues a `site_key` +
`site_secret` to the plugin. The plugin then, on a ~60s loop (WP-Cron schedule + a wakeup ping
when a WP admin has the dashboard open, plus optional long-poll for snappier response):

```
POST https://platform/connector/v1/poll
Headers: X-Site-Key, X-Signature (HMAC-SHA256 of body+timestamp), X-Timestamp
Body:    { capabilities, health }

Response: { commands: [ { id, type, payload, expires_at } ] }
```

The plugin executes each command and posts results back:

```
POST /connector/v1/results   (same signing)
{ command_id, status: ok|failed, data, php_errors_collected }
```

Rules that make this production-grade:

- **Signed + replay-protected**: HMAC signature, timestamp tolerance (±5 min), monotonic
  nonce, short `expires_at` on commands. Never ship plain API-key-only auth.
- **Capability negotiation**: plugin reports WP/PHP versions and supported command types, so
  old plugin versions keep working as the platform adds commands.
- **Idempotent + timeout-bounded**: each command runs under a PHP shutdown handler so a fatal
  error is still reported; results include PHP errors captured since the last poll.
- **Least privilege**: prefer an admin-level *application password* scoped to the connector,
  and document a read-only mode for agencies that want inventory without mutation.
- **PHP on both sides is a gift**: extract the HMAC signing/verification helpers into a tiny
  Composer package both the Laravel app and the plugin depend on — one implementation, tested
  once.

### Command set (v1)

| Command | What it does |
|---|---|
| `inventory.get` | Core/plugin/theme versions, update availability, active state |
| `update.run` | Update one slug (one at a time — see safe-update pipeline) |
| `maintenance.on/off` | Toggle maintenance mode |
| `health.check` | Self-check: front-end 200, no fatals, disk space |
| `phperrors.tail` | Recent deduped PHP warnings/fatals |
| `plugin.deactivate` / `theme.switch` | Recovery actions for broken-site workflows |
| `backup.trigger` | Delegate to installed backup plugin (don't reinvent backups in v1) |

### The safe-update pipeline (this is the killer feature)

What makes WP Umbrella worth paying for is *safe updates* — replicate it as a first-class job
type on the platform side:

1. **Restore point** — DB dump + list of changed files (or delegate to backup plugin).
2. **Update one plugin at a time** (never batch blindly).
3. **Smoke test** — front page + 2–3 key pages return 200, shutdown handler caught no fatals,
   error count didn't spike vs. baseline.
4. **Auto-rollback** on failure, then raise an incident + notification (Laravel Notifications:
   mail/Slack/Discord/ntfy/push channels come nearly free).
5. **Audit-log everything** (who/what triggered it: human, schedule, or AI agent).

### Vulnerability data

Don't build a vuln DB — aggregate one. Evaluate: **Wordfence Intelligence feed** (free CVE
feed), **WPScan API** (free tier, per-slug lookups), **Patchstack**. Cache normalized records
in `vulns_cache` via a scheduled Artisan command, match against inventories on ingest, and
surface "X sites affected by CVE-Y" as a fleet-level view (this cross-site aggregation is what
a spreadsheet can't do).

### Uptime monitoring

v1: platform-side HTTP checks on the **Laravel scheduler** (HEAD/GET, keyword match, SSL expiry
check). Honest limitation: single vantage point. v2: optional distributed "checker" workers that
pull check jobs from the queue. Alerts via Laravel Notifications (email/Slack/Discord/**ntfy**
— open-source push, fits the project ethos) with incident grouping.

---

## 4. Tech stack: Laravel

You know Laravel — that alone wins, and the WordPress world is a PHP world, so future
contributors overlap perfectly with your audience.

| Layer | Choice | Why |
|---|---|---|
| Framework | **Laravel 12+ / PHP 8.3+** | Your expertise; huge ecosystem |
| Dashboard UI | **Filament v4** | Modern admin-grade UI (tables, forms, actions, notifications, dark mode, tenancy) in days not months. v1 pragmatism; customize theme, add bespoke Livewire pages where needed. If you later want a fully bespoke product UI, swap to **Inertia + React** while keeping controllers/policies |
| Auth & teams | Laravel session auth + **Filament multi-tenancy** for orgs, **Policies/Gates** for projects | Orgs = tenants; per-project roles via pivot tables checked in policies |
| DB/ORM | **PostgreSQL + Eloquent** (JSONB casts) | Type-relational core, flexible JSONB edges |
| Jobs/cron | **database queue driver + scheduler** (zero Redis); Horizon + Redis when scale demands | Self-hosters get app + Postgres only |
| Real-time | Polling/SSE first; **Laravel Reverb** (first-party WS) optional later | Live site status without extra infra on day one |
| API layer | **Versioned REST `/api/v1` + Sanctum tokens**, documented via **scramble** (auto OpenAPI) | Same Actions power web UI, API, MCP, and future mobile app |
| MCP | **`laravel/mcp`** (official first-party package) | Tools as invokable classes; integrates with Sanctum/OAuth and your policies |
| Notifications | **Laravel Notifications** | mail/Slack/Discord/ntfy/webhook/push (FCM, APNs) from one API |
| Testing | **Pest** | Laravel-community standard |
| Dev / ship | **Laravel Sail** (dev) / **Docker Compose + FrankenPHP** (prod) | `docker compose up` must be the whole install |

Repo layout (single Laravel app, modular by convention):

```
site-manager/
├─ app/
│  ├─ Models/            Site, Project, Org, Update, Incident, VulnCache …
│  ├─ Actions/           RunSiteUpdate, VerifyCommandSignature, TriggerRollback …
│  │                     ← ALL business logic lives here; every client calls Actions
│  ├─ Filament/          Panels & resources (the dashboard)
│  ├─ Api/               /api/v1 controllers + resources (mobile/API clients)
│  ├─ Mcp/               MCP tools (GetSite, ListPlugins, RequestUpdate …)
│  ├─ Connector/         Poll/results controllers, command registry, HMAC verification
│  └─ Policies/
├─ plugins/
│  └─ plugsent-connector/  PHP plugin (its own repo is also fine for WP community norms)
├─ packages/
│  └─ connector-signing/ Shared HMAC helper (used by app AND plugin)
├─ routes/  database/migrations/  resources/
├─ docker-compose.yml
└─ docs/
```

### Workspaces: one per signup (the tenancy unit)

**Every signup creates a workspace** in the same transaction as the user account:

- You sign up as **betatech** → workspace `betatech` (name + unique slug) is created, and every
  project and site you add lives under it.
- Someone else signs up on your instance → they get their **own isolated workspace**. Workspace
  A's members, projects, sites, tokens, and audit logs are invisible to workspace B — Filament
  multi-tenancy scopes every query by the current workspace, and Policies re-check it.
- Two distinct entry paths: **signup = new workspace**; **invite = join an existing one**
  (workspace owner invites by email → acceptance creates a `workspace_members` row with a
  role). Never conflate them.
- Routing: path-based (`/betatech/…`) is the simple default for self-host; subdomain routing
  (`betatech.plugsent.yourdomain.com`) only if you run the hosted multi-customer mode from day
  one. Filament tenancy supports both.
- Roles: **instance operator** (you, the server owner) vs **workspace owner** (each customer).
  On a single-workspace self-host you're both — keep them separate in the data model anyway.

```
Plugsent instance (your server)
├─ Workspace: betatech        (you)      → projects → sites, members, tokens
├─ Workspace: clientco        (customer) → projects → sites, members, tokens
└─ Workspace: …               (next signup)
```

**Inside a workspace: projects & roles** (the team-assignment requirement):

```
Workspace betatech ─┬─ Projects (Client A retamp, Client B rebuild …)
                    │    └─ Sites (many), each site belongs to exactly ONE project
                    └─ Members ── workspace role: owner / admin / member
                                   + per-project role: lead / maintainer / viewer
```

- Rules like "junior dev may run updates on Project A but only view Project B" resolve as:
  effective permission = workspace role ∩ project role; enforced in Laravel **Policies**, and
  **Sanctum/MCP/API tokens resolve through the same policies** so an agent or mobile user can
  never exceed their owner's reach.
- Every mutating action (by human UI, API, MCP, or mobile) lands in `audit_log` with actor type.

### Consent-gated support access (visible by design, never a silent backdoor)

For operating an instance that hosts other people's workspaces:

1. **Request**: as instance operator you request access to a workspace — scope (view-only or
   act), reason, duration (cap it, e.g. 2 h). This creates a `support_access_requests` row.
2. **Consent**: the workspace owner gets an email + in-app banner: **approve / deny**. Nothing
   happens without an explicit approval.
3. **Session**: on approval a time-boxed support session starts. You operate inside their
   workspace as a *visible* "Support" actor — a banner tells the customer a session is live,
   and every action is audit-logged with `actor_type=support`, your identity, and
   `on_behalf_of` the workspace.
4. **Receipt**: when it expires (or is ended early), the customer sees a summary — who, when,
   what was touched — with a link to the full audit trail.

This is a trust feature, not a hack: Trello, Linear, and Vercel all do consent-gated
impersonation this way, and "support can only enter with your permission, and you'll see
exactly what they did" is a selling point for the hosted mode. On a solo self-host it will
rarely trigger — but building it into the model from Phase 0 means the multi-customer mode
needs no rewrite later.

---

## 5. MCP gateway (the coding-agent component)

Expose the platform as an MCP server via the official **`laravel/mcp`** package at
`https://platform/mcp`. Agents (Claude Code, Cursor, CI bots) authenticate with scoped Sanctum
tokens mapped onto the RBAC model.

Tools: `search_sites`, `get_site`, `list_plugins(site)`, `request_update(site, slug)` → returns
job id (async!), `poll_job`, `get_vulnerabilities(site)`, `get_uptime(site)`,
`get_php_errors(site)`, `toggle_maintenance(site)`, `run_wp_cli(site, command)` — the last one
gated to an admin-only scope with a command allowlist and dry-run mode.

Design rules:
- Long-running operations (updates, backups) return **job IDs** — never block the agent; agents
  poll `poll_job` like humans watch the dashboard.
- Everything is audit-logged with the token identity; per-token rate limits; project-scoped
  tokens by default.
- **The platform's own AI features call these same MCP tools internally.** One code path, and
  "what an agent could do" is exactly visible to users — a great trust + testing story.

---

## 6. Public API & the future mobile app

**Yes — Plugsent is API-first by design, and a mobile app is a straightforward later client.**
The architecture already guarantees it, provided one rule is enforced from day one:

> **All business logic lives in `app/Actions`.** Filament controllers, `/api/v1` controllers,
> MCP tools, queued jobs, and scheduled commands are thin shells over the same Actions. The
> dashboard is then just one client among several — a mobile app becomes "another client," not
> a rewrite.

Concretely:

- **Versioned REST `/api/v1` shipped from Phase 1** (not bolted on later): sites, projects,
  inventories, updates (start + poll job), incidents, uptime, vulnerabilities. Generate the
  OpenAPI spec automatically with **scramble** — free, always-in-sync API docs.
- **Auth for mobile**: Sanctum personal access tokens are made for this — long-lived, scoped,
  revocable per device ("Tom's iPhone"), and they resolve through the same Policies as
  everything else. Cookie sessions stay for the web dashboard; tokens for API/mobile/MCP.
- **Real-time on mobile**: poll `/api/v1` at launch; when Reverb (WebSockets) is enabled,
  the app can subscribe to site status channels instead.
- **Push notifications**: Laravel Notification Channels for **FCM** (Android) and **APNs**
  (iOS) — incidents and update results ring the same bell Slack/email already rings.
- **Mobile stack recommendation**: start with a **responsive PWA** (Filament is desktop-ish;
  add a lightweight Livewire mobile layout for on-call checks). When you want app-store
  presence and push, build with **Expo (React Native)** — one codebase for iOS/Android and it
  consumes `/api/v1` + OpenAPI types cleanly. Flutter is the alternative if you prefer its
  tooling.
- Audit log gains an actor type `mobile`, so "who ran this update" stays answerable no matter
  which client did it.

The same API also serves the MCP gateway and any third-party integrations — one more reason
API-first is the correct core, not an add-on.

---

## 7. AI integration (provider-agnostic, BYO key)

- **"Ask your fleet" chat** — natural-language over MCP tools ("which sites still run that
  plugin with the RCE CVE?").
- **Update summaries & risk scores** — summarize changelogs/readme.txt, factor in plugin
  popularity, last-updated, vuln history → "safe to auto-update" recommendations.
- **Broken-site triage** — after a failed update, feed PHP errors + plugin state to the LLM to
  propose (never auto-run) a recovery plan via `plugin.deactivate`/rollback.
- **Weekly fleet digest** — LLM-written summary of updates/incidents per project.
- Abstraction layer (a simple `LlmDriver` contract) with OpenAI/Anthropic/Google adapters +
  **Ollama** for local models — self-hosters expect no hard cloud-AI dependency.

---

## 8. Open-source strategy

- **License**: connector plugin **GPL-2.0-or-later** (WordPress plugins must be GPL-compatible);
  core platform **AGPL-3.0** if you want to deter closed SaaS clones, or **MIT** for maximum
  adoption. Don't put "WordPress" in the product/domain name (Foundation trademark policy) —
  **Plugsent** already satisfies this.
- Stand on prior art: reuse WP-CLI patterns, credit MainWP, integrate ntfy — communities reward
  this.
- From day one: clear README with screenshots, `docker compose up` quickstart, CONTRIBUTING.md,
  good-first-issue labels, and a versioned connector protocol (call it `connector-protocol v1`)
  so third parties can build alternative connectors.

---

## 9. Roadmap

| Phase | Scope | Outcome |
|---|---|---|
| **0 — Skeleton** ✅ **built** | Laravel + Filament, signup → workspace creation (tenancy), projects/sites models + policies, Sail/Docker | You can sign up (workspace `betatech`), log in, and register a site manually |
| **1 — Connector MVP** ✅ **built** | Pairing codes + `POST /connector/v1/{pair,poll,results}` (HMAC v1, nonce replay protection, throttling), WP plugin (`plugins/plugsent-connector`), `inventory.get`, live "Connected" status + fleet inventory, `scripts/simulate-site.php` E2E harness | Sites pair from the dashboard and report real inventory |
| **1 — Connector MVP** | Plugin pairing (HMAC via shared package), `inventory.get`, Filament site grid, **first `/api/v1` endpoints + OpenAPI docs** | The core loop works, API-first from day one |
| **2 — Updates** | Command queue in UI + API, safe-update pipeline (restore point → update → smoke test → rollback), maintenance mode | ManageWP's heart |
| **3 — Safety net** | PHP error stream, scheduled uptime checks + incidents + notifications (incl. FCM/APNs channels), vuln feed ingestion + fleet CVE view | WP Umbrella parity |
| **4 — Teams & MCP** | Invitations + project-level policies in UI, Sanctum tokens, `laravel/mcp` gateway with 8–10 tools, consent-gated support access, audit log for agents | Agents, staff, and safe support on board |
| **5 — AI & polish** | Chat-over-fleet, update risk summaries, digest emails, dark theme + branding, public launch | The "modern + AI" story |
| **6 — Mobile** | Responsive PWA for on-call checks → Expo (React Native) app on `/api/v1` + push | Plugsent in your pocket |

### Immediate build queue (as prioritized with Adnan)

1. **Admin quick login** — one-click magic login into wp-admin from the dashboard (shipped next).
2. **Plugin/theme management actions** — activate / deactivate / delete / exclude-from-updates.
3. **Team** — invitations UI, role management, per-project assignment (audit log becomes meaningful here).
4. **Uptime monitoring** then **vulnerability feed** (independent of the connector; uses stored inventory).
5. **Safe-update pipeline** (restore points, smoke tests, rollback) around the existing update buttons.
6. Later: automation policies, weekly digests, backups integration, MCP + public API packaging.

Suggested sequencing note: keep the connector protocol frozen at v1 from Phase 1 onward and
extend via new command types — breaking the plugin is the most expensive mistake available.

---

## 10. Naming: **Plugsent** ✅ (decided)

Coined name — plays on "plug(s)" + a sent/guardian feel; unique in English (web search returns
no existing product or meaning). Availability checked **2026-09-03** via registry RDAP — the
rare case where **everything is free**:

| Asset | Status |
|---|---|
| plugsent.com / .dev / .io / .app / .co / .net / .org | **ALL AVAILABLE** |
| github.com/plugsent | FREE |
| npm `plugsent` | FREE |
| Packagist `plugsent/plugsent` | FREE |

**Action: register `plugsent.com` (+ `.dev` for docs, GitHub org, and Packagist vendor) the day
development starts** — free today means nothing tomorrow. Before launch, run a trademark search
in your target classes (software/SaaS) to be thorough.
