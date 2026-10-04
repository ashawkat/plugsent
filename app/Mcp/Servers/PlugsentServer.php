<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetSiteStatus;
use App\Mcp\Tools\ListPendingUpdates;
use App\Mcp\Tools\ListSites;
use App\Mcp\Tools\UpdateSite;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('plugsent')]
#[Version('1.0.0')]
#[Instructions('Plugsent manages WordPress sites: inventory, safe updates, uptime, and security. Start with list-sites to discover site ids, then get-site-status for a full health report. To update software, review list-pending-updates first, then call update-site — items excluded from updates are skipped automatically, and safe-update sites get a restore point with automatic rollback.')]
class PlugsentServer extends Server
{
    protected array $tools = [
        ListSites::class,
        GetSiteStatus::class,
        ListPendingUpdates::class,
        UpdateSite::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
