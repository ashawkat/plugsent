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

## What's new

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

## Features (working today)

- **Workspace-per-signup tenancy** — every signup gets an isolated workspace with slug URLs
  (`/app/betatech/…`); invite teammates with workspace and per-project roles.
- **Projects & Sites** — organize sites into projects, with workspace-scoped authorization on
  every read and write.
- **One-click pairing** — generate a 15-minute pairing code from the dashboard, paste it into
  the [connector plugin](https://github.com/ashawkat/plugsent-connector), done.
- **Live inventory** — WordPress, plugin, and theme versions with update availability, refreshed
  on every check-in.
- **Safe updates** — restore point (files + streamed database dump) → update → smoke test →
  automatic rollback. Core updates stay plain; old connectors keep the classic update path.
- **Plugin/theme actions** — activate, deactivate, delete, theme switching, update exclusions,
  manual restore — with capability-based UI (old connectors simply don't show new buttons).
- **Uptime monitoring** — scheduled external checks, downtime incidents, email alerts, a
  pause/resume toggle per site, domain & SSL expiry tracking, and a 30-day uptime-rate view.
- **Security & hardening** — Wordfence vulnerability matching with per-vulnerability detail
  popups, a 14-check security score, and six connector-enforced hardening toggles per site.
- **Connector protocol v1** — HMAC-SHA256 signed requests, timestamp tolerance, nonce replay
  protection, instant revocation, 120 req/min throttling.
- **Revocable by design** — "Revoke access" kills the site's credentials on its next poll;
  rotation happens through the signed channel without downtime.

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

## Architecture

```
┌──────────────────────────────────────────────┐
│          Plugsent control plane (this repo)  │
│  Laravel 13 · Filament dashboard · REST API  │
│  uptime checker · vulnerability cache · RBAC │
│   ┌──────────────┐  ┌─────────────────────┐  │
│   │ MCP gateway  │  │  AI layer (BYO LLM) │  │
│   └──────────────┘  └─────────────────────┘  │
└──────────▲───────────────────────▲────────────┘
           │ outbound, HMAC-signed │ MCP tools
   ┌───────┴──────────┐    ┌───────┴───────────┐
   │ connector plugin │    │ coding agents     │
   │ (submodule)      │    │ Claude Code, etc. │
   └──────────────────┘    └───────────────────┘
```

- **Sites poll the server — never the reverse**, so sites behind firewalls and staging auth
  just work.
- All business logic lives in `app/Actions`; the dashboard, API, and future MCP/mobile clients
  are thin shells over the same actions.
- The full architecture, schema, and design decisions live in [PLAN.md](./PLAN.md).

## Roadmap

| Phase | Status | Scope |
|---|---|---|
| 0 — Skeleton | ✅ shipped | Laravel + Filament, tenancy, projects/sites, policies |
| 1 — Connector MVP | ✅ shipped | Pairing, signed poll loop, inventory, connect UI |
| 2 — Safe updates | ✅ shipped | Restore point → update → smoke test → auto-rollback, update audit trail |
| 3 — Safety net | ✅ shipped | ✅ uptime + incidents + email alerts · ✅ Wordfence vulnerability feed & matching |
| 3.5 — Security | ✅ shipped | Security score, health checks, connector-enforced hardening toggles, site tabs, history audit trail, quick-switcher |
| 4 — Teams & MCP | 🟡 half shipped | ✅ invitations, roles, project-level RBAC · ⬜ MCP gateway, public API |
| 5 — AI | planned | Chat over your fleet, update risk summaries, weekly digests |
| 6 — Mobile | planned | PWA first, then an Expo app on the same API |
| 7 — Parity extras | ⬜ next | Performance (PageSpeed) monitoring, broken-link checking, backups, malware scanning, activity log beyond commands, alerting rules |

## Development

```bash
php artisan test        # 81 tests: protocol, signing, isolation, uptime, safe updates, Plugin Check audit
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
