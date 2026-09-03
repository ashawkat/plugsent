# Plugsent

Self-hosted, open-source WordPress fleet management — an alternative to WP Umbrella /
ManageWP. Connect WordPress sites, manage plugins/themes/updates, monitor uptime, scan for
vulnerabilities, organize sites into projects, assign team members, and let coding agents
operate everything through MCP.

Read [PLAN.md](./PLAN.md) for the full architecture and roadmap.

## Status: Phase 1 — connector MVP ✅

Working today:

- Filament dashboard with **workspace-per-signup tenancy** — every signup creates its own
  isolated workspace (`workspaces`, slug-based URLs like `/app/betatech/...`)
- **Projects** and **Sites** resources (CRUD, tenant-scoped, policies enforced)
- **WordPress connector** (protocol v1): issue a pairing code from the dashboard's
  **Connect site** page, install `plugins/plugsent-connector` on the WP site, paste the server URL
  + code — the site then polls your server every minute (outbound-only, HMAC-signed,
  replay-protected) and reports its full plugin/theme/core inventory
- Sites flip to **Connected** automatically, with "Refresh inventory" and "Revoke access"
  actions per site
- Test the full flow without WordPress: `php scripts/simulate-site.php http://127.0.0.1:8000 <code>`

## Requirements

- PHP 8.3+ with `pdo_sqlite` (dev) or `pdo_pgsql`
- Composer

## Quickstart

```bash
composer install
cp .env.example .env        # sqlite is the default local database
php artisan migrate
php artisan serve
```

Then open <http://127.0.0.1:8000/app/register> and sign up — your workspace is created
automatically and you land inside it.

### Using PostgreSQL instead of SQLite

```bash
docker compose up -d          # starts postgres:16 on :5432
# set DB_CONNECTION=pgsql (and credentials) in .env — see docker-compose.yml header
php artisan migrate
```

## Tests

```bash
php artisan test
```

The suite covers: signup creating the workspace + owner membership, slug uniqueness,
cross-workspace isolation (policies + tenant access), rendering of all resource pages,
signing vectors, **plugin-vs-package signer agreement**, and the full connector protocol
(pair → poll → results, including tampered/stale/replayed/revoked rejection).

## Connector protocol (v1)

- Pairing: one-time code (15 min, single-use) → `POST /connector/v1/pair` → site key + secret
- Ongoing: `POST /connector/v1/poll` and `POST /connector/v1/results`, HMAC-SHA256 signed
  over `"{timestamp}.{body}"` with ±5 min tolerance and nonce replay protection
- Shared reference implementation: `packages/connector-signing` (the WP plugin vendors a copy
  that CI tests against the same vectors)

## Layout

- `app/Actions` — business logic (`CreateWorkspaceForUser`, `IssuePairingCode`,
  `EnqueueSiteCommand`, `ProcessInventoryResult`)
- `app/Filament` — dashboard (auth pages, Connect site page, Projects & Sites resources)
- `app/Http/Controllers/Connector` — pair / poll / results endpoints
- `app/Http/Middleware/AuthenticateConnector` — HMAC verification + replay protection
- `packages/connector-signing` — protocol v1 signing reference implementation
- `plugins/plugsent-connector` — the WordPress connector plugin (author: BetaTech)
- `scripts/simulate-site.php` — end-to-end connector simulator (no WordPress needed)
