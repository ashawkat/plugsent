<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetSiteStatus;
use App\Mcp\Tools\ListPendingUpdates;
use App\Mcp\Tools\ListSites;
use App\Mcp\Tools\RescanInventory;
use App\Mcp\Tools\UpdateSite;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('plugsent')]
#[Version('1.1.0')]
#[Instructions('Plugsent manages WordPress sites: inventory, safe updates, uptime, and security. Start with list-sites to discover site ids, then get-site-status for a full health report. To update software, review list-pending-updates first, then call update-site — items excluded from updates are skipped automatically, and safe-update sites get a restore point with automatic rollback. If update lists look stale, call rescan-inventory and check again after the sites\' next check-in. Tokens may be read-only: update-site and rescan-inventory refuse them until the owner generates a token with write access (My account → MCP access). Every command an agent queues is stamped with the account and token name in the site\'s activity history.')]
class PlugsentServer extends Server
{
    protected array $tools = [
        ListSites::class,
        GetSiteStatus::class,
        ListPendingUpdates::class,
        UpdateSite::class,
        RescanInventory::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
