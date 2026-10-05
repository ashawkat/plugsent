<!--
Title options (pick one):
  1. Why I quit WP Umbrella and ManageWP (and built my own fleet manager)
  2. Our fleet dashboard said "zero updates." It was lying.
  3. Plugsent goes autopilot: a WordPress fleet manager that fixes itself

Suggested meta description:
  "I moved off WP Umbrella and ManageWP because per-site pricing, closed
  source code, and someone else's cloud stopped making sense for my fleet.
  Then my own dashboard lied to me — said zero updates when 15 were
  pending. Here's what I built to fix it: Plugsent, a self-hosted,
  open-source WordPress fleet manager that now keeps itself running."
Delete this block before publishing.
-->

# Why I quit WP Umbrella and ManageWP (and built my own fleet manager)

For years I ran WordPress client fleets the way most agencies do: a SaaS dashboard — first ManageWP, later WP Umbrella — watching over updates, uptime, and security from someone else's cloud.

I'm not here to trash either one. They're good products, and they shaped how I think about fleet management. But over time, four things kept bothering me, and eventually they added up to "I can't keep doing this":

**1. Per-site pricing that punishes growth.** Every client you sign is a new monthly line item, forever. The tool that makes you money as an agency quietly taxes you for succeeding. A fleet of 40 sites costs 4× a fleet of 10 — for software that, honestly, is mostly polling sites and rendering a dashboard.

**2. The good parts are behind paywalls.** White-labeling, deeper security features, more frequent checks — the things that actually differentiate you to your clients always seemed to live one tier up. I found myself paying for capabilities I didn't need to unlock the two I did.

**3. My fleet's data lived on someone else's server.** Which sites I run, what's installed on them, their vulnerabilities, their update history — that's my business intelligence. It sat in a third-party cloud, exportable only on their terms.

**4. I couldn't fully answer "what does this plugin do?"** Clients — the security-conscious ones especially — asked what the connector on their site actually does. With closed-source tools the honest answer was "trust the vendor." For a connector with deep access to a WordPress install, that never sat right with me.

So I did the thing you're not supposed to do: I built my own. It's called **Plugsent** — self-hosted, open source, and shaped by everything above. It pairs with your sites over an outbound-only, HMAC-signed channel (no firewall holes, no stored admin passwords), the control plane is MIT-licensed, and the connector plugin is GPL-2.0 so anyone can read exactly what runs on a client's site.

I'll get to why the launch story involves my own dashboard lying to my face. First, what shipped.

## The story that made the release: my dashboard said "zero updates"

Recently I asked Plugsent a simple question: *any pending updates?* Five sites. The answer came back clean: **no updates pending anywhere.**

It was wrong. A forced rescan told a different story: **15 pending updates across four sites**, including a WordPress core update, a Bricks theme update, and a stack of plugins I actually care about.

Nothing was haunted. The connectors only collect inventory when the server asks, and nothing was asking on a schedule. The refresh buttons worked — but every "you're up to date" quietly rotted the moment someone published a new plugin version. The classic monitoring trap: the dashboard shows the last known truth, and nobody notices the truth going stale.

And while fixing that, I found the daily "updates available" digest email had been silently emailing *nobody* since launch — a query bug where two Laravel helpers combined into a constraint that matched zero users. Feature shipped, tests green, emails into the void. Every lesson in this post came from that one dishonest answer.

That discovery became the biggest Plugsent release yet.

## The scheduler that installs itself

Everything above traces to one root cause: a fresh install has no cron. Hourly scans, the daily digest, the weekly vulnerability-feed sync — all depend on Laravel's scheduler running every minute, and nothing set that up. Forget the cron line (I did), and the app keeps smiling while doing no scheduled work.

Now the app does it itself. The scheduler writes a heartbeat every minute. Whenever a paired site checks in, the app checks that heartbeat — and if the scheduler is dead, it installs its own `schedule:run` crontab entry, logs it, and moves on. No setup step, no "did you remember the cron?" Fresh installs set themselves up the moment the first site pairs. There's also `php artisan plugsent:ensure-scheduler` for the belt-and-suspenders crowd.

## Hourly scans, so "up to date" means up to date

Plugsent now re-scans every connected site's inventory once an hour, skipping sites with a scan already in flight. That's the actual fix for the lying dashboard: the connector only collects inventory when asked — so now it's asked on a schedule.

## One updates email a day, at a time you pick

Hourly scans could easily mean hourly spam — and hourly SMTP bills. My first cut sent an email whenever a site's pending-update set changed, and it worked, but I quickly realized the economics were wrong: every email costs SMTP quota, and "one new plugin version" rarely justifies a message. So Plugsent sends exactly **one** updates email per day: a digest listing every site with pending plugin, theme, and core updates, at a time you configure in Settings. You pick morning coffee o'clock; nothing fires before it, nothing repeats after it, and workspaces with nothing pending don't get mail at all. Per-user opt-outs still apply.

While unifying the email system I also moved everything onto one branded template — uptime alerts, security alerts, the updates digest, password confirmations, workspace invitations — set in self-hosted Google Sans, with system-font fallbacks for clients that block web fonts. No more Laravel-default invitation emails.

## Chat with your fleet over MCP

Plugsent speaks MCP (`laravel/mcp`, streamable HTTP), so chat clients and coding agents can work your fleet directly, through the same permissions as the dashboard:

- **list-sites** — the fleet at a glance: scores, uptime, pending updates, vulnerabilities
- **get-site-status** — the full report for one site, failing security checks included
- **list-pending-updates** — exactly what's outdated, exclusions respected
- **update-site** — queue plugin/theme/core updates through the safe-update pipeline
- **rescan-inventory** — trigger a fresh scan for one site or the whole fleet

Setup is a token from **My account → MCP access** plus three lines of client config:

```json
{
  "mcpServers": {
    "plugsent": {
      "url": "https://your-plugsent.example.com/mcp/plugsent",
      "headers": { "Authorization": "Bearer <your-token>" }
    }
  }
}
```

Then, from your terminal: *"check all my sites for updates and update the SEOPress on Pathfinder."* Every tool call runs through the same workspace and project policies the UI uses — a member's token can't touch a lead-only site.

## Everything Plugsent does today

The complete tour, grouped by what you'd actually do with it:

**Connect & organize**
- One-click pairing — generate a 15-minute code in the dashboard, paste it into the connector plugin, done. No admin passwords stored, ever.
- Outbound-only connector protocol — HMAC-SHA256 signed requests, timestamp tolerance, nonce replay protection, instant revocation, 120 req/min throttling. Sites behind firewalls and staging auth just work.
- Workspaces & teams — every signup gets an isolated workspace with slug URLs; email invitations with one-step joins; workspace and per-project roles.
- Projects & sites — group client sites into projects with workspace-scoped authorization on every read and write.

**Update with confidence**
- Safe updates — the full pipeline: files + streamed database restore point → update → smoke test → automatic rollback if the site stops answering. Core updates stay plain.
- Plugin/theme management — activate, deactivate, delete, switch themes, per-item "exclude from updates," and a manual restore for updates that "succeed" but misbehave.
- Capability-aware UI — old connectors simply don't show buttons they can't execute.
- One-click admin login — single-use magic login into wp-admin from the site page.

**Monitor**
- Uptime monitoring — checks every 5 minutes, incidents after two consecutive failures, 🔴/🟢 email alerts, pause/resume per site.
- Domain & SSL expiry — domain expiry via RDAP, SSL expiry read from the served certificate, cached daily.
- 30-day uptime analytics — uptime-rate percentage with per-day bars and an incident timeline.
- Fleet dashboard — a workspace-wide security-score ring, KPI cards with 14-day trend sparklines and week-over-week deltas, a "needs attention" table that ranks the worst sites *with reasons*, and a fleet-wide activity feed.

**Secure**
- Security score — 14 health checks (WP_DEBUG, SSL, WP/PHP support, inactive and vulnerable software, hardening) rolled into a score out of 100, with one-click **Fix** actions.
- Hardening toggles — the connector enforces six protections on the site: hide WP version, block user enumeration, mask login errors, disable the file editor, security headers, disable XML-RPC.
- Vulnerability intelligence — a local mirror of the Wordfence Intelligence feed matched against every site's inventory, with per-vulnerability detail popups (severity, CVSS, CVE, affected range, fix status, advice).

**Stay informed**
- Daily updates digest — the single updates email, once a day at a time you pick in Settings.
- Daily updates digest — once a morning, every site with pending updates and exact versions.
- Uptime & security alerts — site-down/site-recovered and vulnerability-growth alerts, throttled sensibly.
- Email preferences — every category has a per-user opt-out, respected by every sender.

**Automate & integrate**
- Self-healing scheduler — the app installs and repairs its own cron on connector check-ins.
- Hourly inventory refresh — the fleet's data stays honest without anyone pressing refresh.
- MCP gateway — five policy-scoped tools for chat clients and coding agents.
- REST API — the connector protocol is a versioned, signed API; a public dashboard API is next on the roadmap.

**Administer**
- My account — profile, password changes (signs out other sessions), TOTP two-factor login with eight recovery codes.
- Settings UI — SMTP with a test send, vulnerability feed sync with cooldown; values override `.env`.
- Self-hosted by design — SQLite out of the box (PostgreSQL via Docker), self-hosted fonts, no third-party SaaS dependency. MIT control plane, GPL-2.0 connector, 120 tests covering the protocol, pipeline, permissions, and self-healing.

## What's next

The parity extras that the paid tiers of the world keep behind paywalls — PageSpeed monitoring, broken-link checking, backups, malware scanning, and custom alerting rules — are up next on the roadmap. A public REST API for the dashboard follows the connector protocol's lead.

---

**Try it:** Plugsent is open source at [github.com/ashawkat/plugsent](https://github.com/ashawkat/plugsent). Clone it, sign up, pair a site (there's a connector simulator if you don't have WordPress handy), and connect your chat client over MCP. If you run WordPress fleets for a living — or you're paying per site to do it — I'd genuinely love to hear what's missing. Open an issue or drop me a line.
