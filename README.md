<p align="center">
  <img src="public/logo.svg" width="300" alt="Plugsent">
</p>

<p align="center">
  <strong>Self-hosted, open-source fleet management for WordPress.</strong><br>
  Connect every site you run — inventory, safe updates, uptime, vulnerabilities, teams, and coding agents via MCP.
</p>

<p align="center">
  <a href="#license"><img src="https://img.shields.io/badge/license-MIT-blue" alt="License: MIT"></a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20" alt="Laravel 13">
  <a href="https://github.com/ashawkat/plugsent-connector"><img src="https://img.shields.io/badge/connector-GPL--2.0-green" alt="Connector: GPL-2.0"></a>
</p>

---

Plugsent is an alternative to WP Umbrella and ManageWP that **you host yourself**. Pair your
WordPress sites with a one-time code, and they check in over an outbound-only, HMAC-signed
channel — no firewall rules, no inbound ports, and never your WordPress admin password.

## Screenshots

| | |
|---|---|
| ![Plugsent login](docs/screenshots/login.png) | ![Plugsent dashboard](docs/screenshots/dashboard.png) |
| ![Plugsent sites](docs/screenshots/sites.png) | ![Plugsent site overview](docs/screenshots/site-overview.png) |
| ![Plugsent activity and uptime](docs/screenshots/dashboard-activity.png) | |

## What's new

- **Oct 2026 — autopilot** — Plugsent keeps itself running and keeps you posted:
  - **Self-installing scheduler** — the scheduler writes a heartbeat every minute; when a
    connector checks in and the heartbeat is stale, the app installs its own
    `php artisan schedule:run` cron entry (production only, one repair attempt per hour,
    logged). Fresh installs set themselves up on the first site check-in — no manual cron —
    and `php artisan plugsent:ensure-scheduler` does the same thing by hand.
  - **Hourly inventory refresh** — `plugsent:refresh-inventory` re-scans every connected site
    each hour (skipping sites with a scan already outstanding), so pending-update lists stop
    drifting stale. The connector only scans when asked — now it is asked on a schedule.
  - **Post-update verification** — after every update batch a fresh scan judges the result:
    if the site still offers the same old version, the run flips to **failed** with an
    explanation (typically a missing plugin license) instead of silently claiming success.
  - **One updates email a day** — the daily digest is the single updates email: every site
    with pending plugin/theme/core updates, sent once a day at a time configured in Settings
    (Settings → Notifications). No per-update emails in between, so SMTP cost stays
    predictable; users can still opt out per profile, and workspaces with nothing pending
    don't get mail at all.
  - **Branded everything** — every email (uptime, security, updates, password, invitations)
    shares the Plugsent-styled layout, now set in self-hosted **Google Sans**; the workspace
    invitation moved onto it too.
- **Oct 2026 — MCP gateway** — plug Plugsent into any chat client or coding agent:
  `laravel/mcp` exposes a streamable-HTTP MCP server at `/mcp/plugsent`, authenticated with a
  per-user bearer token generated on **My account → MCP access**. Five tools — `list-sites`,
  `get-site-status`, `list-pending-updates`, `update-site`, and `rescan-inventory` — resolve
  through the same site policies as the dashboard, so a token can never out-perform its
  owner. See [Chat with Plugsent over MCP](#chat-with-plugsent-over-mcp).
- **Sep 2026 — the fleet dashboard** — the home page became a real command center:
  - **Fleet health at a glance** — a security-score ring for the whole workspace, KPI cards
    (sites online, pending updates, open vulnerabilities by severity, uptime) with 14-day trend
    sparklines and week-over-week deltas from a nightly snapshot job, a **Needs attention** table
    that ranks the worst sites with the reason each one is on the list, the 14 security checks
    aggregated across the fleet as worst-first pass-rate bars, a fleet-wide activity feed, and
    per-site 30-day uptime strips.
  - **Sites list upgraded** — grouped by project, risk-ordered (disconnected first, lowest score
    next), severity dots per vulnerability class, and inline 30-day uptime mini-strips.
  - **Site overview rebuilt** — the Overview tab now leads with the score ring, failing checks
    with reasons, hardening **quick wins** with point math, pending updates with `from → to`
    versions and one-click Update all, and the uptime card with rate, strip, and SSL/domain
    expiry.
- **Jul 2026 → Sep 2026** — the platform grew from "inventory viewer" to a real manager:
  - **Safe updates** — every plugin/theme update runs the full pipeline: files + database restore
    point → update → site smoke test → **automatic rollback** if the site stops answering. Plus a
    manual **Restore backup** action for updates that "succeed" but misbehave.
  - **Uptime monitoring** — every enabled site is checked every 5 minutes (no cron needed — checks
    piggyback on connector check-ins); downtime incidents open after two consecutive failures and
    the workspace gets 🔴 down / 🟢 recovered emails through the built-in SMTP settings.
  - **Plugin/theme management** — activate, deactivate, delete, switch themes, and per-item
    "exclude from updates" straight from the dashboard. The connector itself can never be managed
    remotely.
  - **Teams** — email invitations with a one-step join (invitees just pick a name and password),
    workspace + per-project roles.
  - **Settings UI** — configure SMTP (with test email) from the dashboard; values override `.env`.
  - **Admin quick login** — one-click single-use magic login into wp-admin.
- **Sep 2026** — the WP Umbrella-class feature set landed:
  - **Security** (connector 0.13.0+) — a per-site security **score out of 100** built from 14
    health checks (WP_DEBUG, SSL, WP/PHP support status, inactive software, vulnerable software,
    plus the six hardening protections), an "attention needed" list with one-click **Fix**
    buttons, and **hardening toggles** the connector enforces on the site: hide WP version,
    block user enumeration, mask login errors, disable the file editor, send security headers,
    disable XML-RPC. Toggle state is persisted on the site (DB-backed option) and survives
    restarts; all scoring logic lives on the panel so it evolves without touching sites.
  - **Vulnerability intelligence** — a local mirror of the **Wordfence Intelligence feed**
    (free API key, refreshed from Settings with a 1-hour cooldown, runs as a detached
    background job) matched against every site's inventory. Sites show "⚠ N vulnerable"
    badges, each opening a detail popup (severity, CVSS, CVE link, affected range, fix
    status, description, references, what-to-do advice), and the Security tab lists every
    affected plugin/theme per site with a "fix available" hint.
  - **Site pages became tabs** — Overview / Plugins / Themes / Core / Uptime / Security /
    History, like WP Umbrella. Overview summarizes security, updates, and uptime; **History**
    is an audit trail of the last 40 commands (updates, rollbacks, restores, logins, scans).
    Tabs are URL-addressable (`?tab=security`) and a **quick-switcher** dropdown (search,
    keeps the current tab) jumps between sites from anywhere on a site page.
  - **Uptime revamp** — status cards (current status, **domain expiry via RDAP**, **SSL expiry
    read from the served certificate** — cached daily, last check), a **30-day uptime-rate**
    percentage with per-day bars computed from incidents, and an incident timeline instead of
    a table.
- **Sep 2026, part 2** — emails, accounts, and account security:
  - **Branded email templates** — one shared Plugsent-styled HTML layout powers every email:
    🔴 site-down / 🟢 recovered uptime alerts, 🛡 **security alerts** (sent when a site's
    vulnerability count grows, throttled to one email per site per day), a **daily
    "updates available" digest** (once a day per workspace — every site with pending updates
    and the exact versions, no per-update spam), and a **"your password was changed"**
    confirmation with a "this wasn't you" warning.
  - **My account page** — every user can update their name/email, change their password
    (requires the current password, signs out other browser sessions, and emails a
    confirmation), and set **email preferences** — uptime, security, and updates emails each
    have an opt-out, respected by every sender.
  - **Two-factor authentication (TOTP)** — enable from My account by scanning a QR code with
    any authenticator app; sign-in then requires a 6-digit code after the password (built on
    Filament's multi-factor challenge system). Eight one-time **recovery codes** are shown at
    setup and can be used instead of a code if the device is lost; disabling 2FA or
    regenerating codes requires the account password. Secrets are encrypted at rest, recovery
    codes hashed.
  - **Show/hide (eye) icons** on every password field — registration, login, password reset,
    invite join, and the account page.

## Features (working today)

- **Workspace-per-signup tenancy** — every signup gets an isolated workspace with slug URLs
  (`/app/betatech/…`); invite teammates with workspace and per-project roles.
- **Projects & Sites** — organize sites into projects, with workspace-scoped authorization on
  every read and write.
- **One-click pairing** — generate a 15-minute pairing code from the dashboard, paste it into
  the [connector plugin](https://github.com/ashawkat/plugsent-connector), done.
- **Live inventory** — WordPress, plugin, and theme versions with update availability,
  refreshed on every check-in and re-scanned hourly across all connected sites.
- **Safe updates** — restore point (files + streamed database dump) → update → smoke test →
  automatic rollback. Core updates stay plain; old connectors keep the classic update path.
- **Plugin/theme actions** — activate, deactivate, delete, theme switching, update exclusions,
  manual restore — with capability-based UI (old connectors simply don't show new buttons).
- **Uptime monitoring** — scheduled external checks, downtime incidents, email alerts, a
  pause/resume toggle per site, domain & SSL expiry tracking, and a 30-day uptime-rate view.
- **Security & hardening** — Wordfence vulnerability matching with per-vulnerability detail
  popups, a 14-check security score, and six connector-enforced hardening toggles per site.
- **Emails & accounts** — branded templates (Google Sans) for uptime/security/update
  emails with per-user opt-outs; a My account page (profile, password change, 2FA setup);
  TOTP two-factor login with recovery codes; show/hide toggles on all password fields.
- **Self-healing scheduler** — hourly scans, digests, and feed syncs run on the Laravel
  scheduler, and the app installs its own `schedule:run` cron entry when it notices the
  scheduler is missing (or run `php artisan plugsent:ensure-scheduler` yourself).
- **Connector protocol v1** — HMAC-SHA256 signed requests, timestamp tolerance, nonce replay
  protection, instant revocation, 120 req/min throttling.
- **Revocable by design** — "Revoke access" kills the site's credentials on its next poll;
  rotation happens through the signed channel without downtime.
- **MCP gateway** — chat clients and coding agents read site stats, trigger inventory
  rescans, and queue updates through `laravel/mcp` with Sanctum bearer tokens
  (My account → MCP access).

## Quickstart

```bash
git clone --recurse-submodules https://github.com/ashawkat/plugsent.git
cd plugsent
composer install
cp .env.example .env        # SQLite is the default local database
php artisan key:generate
php artisan migrate
php artisan serve
```

Open <http://127.0.0.1:8000/app/register>, sign up, and your workspace is created instantly.

> Prefer PostgreSQL? `docker compose up -d` starts one on :5432 — see the header of
> [docker-compose.yml](docker-compose.yml) for the `.env` lines.

### Connect a WordPress site

1. In the dashboard, open **Connect site**, fill in the site's name/URL/project, and copy the
   pairing code.
2. On the WordPress site, install the
   [Plugsent Connector](https://github.com/ashawkat/plugsent-connector) plugin and paste the
   **Server URL** + code under **Settings → Plugsent Connector**.
3. The site checks in within a minute and flips to **Connected** with its full inventory.

No WordPress site at hand? Simulate one:

```bash
php scripts/simulate-site.php http://127.0.0.1:8000 <pairing-code>
```

### Self-hosting notes

- **Scheduler** — hourly inventory rescans, the daily updates digest, the weekly
  vulnerability-feed sync, and nightly fleet snapshots all run on the Laravel scheduler
  (`php artisan schedule:run` every minute). You do not have to install that cron yourself:
  once a paired site checks in, Plugsent notices a missing scheduler and installs the crontab
  entry itself (production environments only; on hosts that block shell access from PHP it
  logs a warning instead — run `php artisan plugsent:ensure-scheduler` over SSH there).
- **Queues** — mail sends through your queue connection as configured; `sync` works out of
  the box for small fleets, and a `database` queue with a worker scales further.
- **SMTP** — configure mail (with a test send) from **Settings** in the dashboard; those
  values override `.env`.
- **Fonts** — emails and the dashboard render in self-hosted Google Sans (served from
  `public/fonts`); no external font CDN calls.

### Chat with Plugsent over MCP

Plugsent is an MCP server ([`laravel/mcp`](https://github.com/laravel/mcp), streamable HTTP),
so you can ask a chat client or coding agent things like *"how are my sites doing?"* or
*"update the plugins on client-a.test"*.

1. In the dashboard open **My account → MCP access** and click **Generate token** — copy it,
   it is shown once (generating again rotates the token; **Revoke** kills it immediately).
2. Point your client at the URL shown on that page (by default
   `http://127.0.0.1:8000/mcp/plugsent`) and send the token as a bearer header:

   ```json
   {
     "mcpServers": {
       "plugsent": {
         "url": "http://127.0.0.1:8000/mcp/plugsent",
         "headers": {
           "Authorization": "Bearer <your-token>"
         }
       }
     }
   }
   ```

3. The client discovers five tools: **list-sites** (inventory with scores, uptime, and
   pending-update/vulnerability counts), **get-site-status** (full report: failing security
   checks, vulnerabilities with severity, SSL/domain expiry), **list-pending-updates**
   (respects update exclusions), **update-site** (queues plugin/theme/core updates with the
   same safe-update pipeline and permissions as the dashboard), and **rescan-inventory**
   (queues a fresh inventory scan for one site or every connected site you may update —
   results arrive on the sites' next check-in).

**Token scopes & the audit trail.** New tokens are **read-only by default** — they can
inspect sites and pending updates but `update-site` and `rescan-inventory` refuse them.
Untick "Read-only token" when generating to grant write access. Every command an agent
queues is stamped **via MCP** with your account and token name, shown on the site's
History tab — so a client-fleet trail shows exactly which agent triggered which update.
Legacy tokens (pre-scope) keep full access; regenerate to switch.

Requests are throttled to 120/min per token, and every tool call runs through the same site
policies the UI uses — a member-role token cannot update a lead-only site.

## Architecture

```
┌──────────────────────────────────────────────┐
│          Plugsent control plane (this repo)  │
│  Laravel 13 · Filament dashboard · REST API  │
│  uptime checker · vulnerability cache · RBAC │
│  scheduler · email notifications             │
│   ┌────────────────────────────────────────┐ │
│   │  MCP gateway (list · status · updates  │ │
│   │  · rescan)                             │ │
│   └────────────────────────────────────────┘ │
└──────────▲───────────────────────▲────────────┘
           │ outbound, HMAC-signed │ MCP tools
   ┌───────┴──────────┐    ┌───────┴───────────┐
   │ connector plugin │    │ coding agents     │
   │ (submodule)      │    │ chat clients      │
   └──────────────────┘    └───────────────────┘
```

- **Sites poll the server — never the reverse**, so sites behind firewalls and staging auth
  just work.
- All business logic lives in `app/Actions`; the dashboard, API, and MCP clients are thin
  shells over the same actions.
- The full architecture, schema, and design decisions live in [PLAN.md](./PLAN.md).

## Roadmap

| Phase | Status | Scope |
|---|---|---|
| 0 — Skeleton | ✅ shipped | Laravel + Filament, tenancy, projects/sites, policies |
| 1 — Connector MVP | ✅ shipped | Pairing, signed poll loop, inventory, connect UI |
| 2 — Safe updates | ✅ shipped | Restore point → update → smoke test → auto-rollback, update audit trail |
| 3 — Safety net | ✅ shipped | ✅ uptime + incidents + email alerts · ✅ Wordfence vulnerability feed & matching |
| 3.5 — Security | ✅ shipped | Security score, health checks, connector-enforced hardening toggles, site tabs, history audit trail, quick-switcher |
| 3.6 — Accounts & emails | ✅ shipped | Branded email templates (uptime/security/daily updates digest/password), My account page with email preferences, TOTP 2FA with recovery codes, revealable password fields |
| 3.7 — Autopilot | ✅ shipped | Hourly inventory refresh, one configurable-time daily updates email, self-installing scheduler, `rescan-inventory` MCP tool, Google Sans email branding, post-update verification (connector "success" is checked against fresh inventory — no-update-taken runs flip to failed) |
| 4 — Teams & MCP | 🟡 half shipped | ✅ invitations, roles, project-level RBAC · ✅ MCP gateway (Sanctum tokens, list/status/updates/rescan tools) · ⬜ public REST API |
| 5 — Parity extras | 🔜 coming soon | Performance (PageSpeed) monitoring, broken-link checking, backups, malware scanning, activity log beyond commands, alerting rules |

## Development

```bash
php artisan test        # 120 tests: protocol, signing, isolation, uptime, safe updates, MCP gateway, scheduler self-heal, Plugin Check audit
vendor/bin/pint         # code style
```

- `packages/connector-signing` — the protocol v1 signing reference implementation.
- `plugins/plugsent-connector` — the WordPress plugin (git submodule).
- `scripts/simulate-site.php` — end-to-end connector simulator, no WordPress needed.

## Contributing

PRs are welcome! Please make sure `php artisan test` passes. The connector plugin is GPL-2.0
and follows the [WordPress Plugin Check](https://wordpress.org/plugins/plugin-check/) rules —
its static audit runs with the main suite.

## Security

Plugsent pairs sites with per-site key pairs and never stores WordPress admin passwords. If
you find a vulnerability, please use GitHub's **Report a vulnerability** (Security tab) rather
than a public issue.

## License

- The Plugsent control plane is licensed under the **MIT License** — see [LICENSE](LICENSE).
- The WordPress connector plugin is **GPL-2.0-or-later**, as WordPress plugins must be.
- [Google Sans](https://fonts.google.com/specimen/Google+Sans) is © Google, redistributed under
  the [SIL Open Font License 1.1](https://openfontlicense.org).

## Acknowledgements

Built on Laravel & Filament. Inspired by the workflows of MainWP, ManageWP, and WP Umbrella —
with the parts they keep behind a paywall, opened up.
