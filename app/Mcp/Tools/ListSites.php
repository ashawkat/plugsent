<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\UserSites;
use App\Models\Site;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the WordPress sites the caller can see, with connection status, security score, pending update and vulnerability counts. Use a site id from this list with the other Plugsent tools.')]
class ListSites extends Tool
{
    public function handle(Request $request): Response
    {
        $sites = app(UserSites::class)($request->user());

        if ($sites->isEmpty()) {
            return Response::text('No sites are connected to this Plugsent account yet.');
        }

        $lines = $sites->map(fn (Site $site): string => sprintf(
            '[%d] %s (%s)%s — %s · security score %s · %d update(s) pending · %d item(s) with known vulnerabilities · uptime %s',
            $site->getKey(),
            $site->name,
            $site->url,
            $site->project !== null ? ', project: '.$site->project->name : '',
            $site->isConnected()
                ? 'connected, last seen '.($site->last_seen_at?->diffForHumans() ?? 'never')
                : ($site->status ?? 'disconnected'),
            $site->security_score !== null ? $site->security_score.'/100' : 'not scanned',
            $site->inventory->where('update_available', true)->count(),
            (int) $site->inventory->sum('vuln_count'),
            $site->uptime_enabled ? ($site->uptime_status ?? 'unknown') : 'not monitored',
        ));

        return Response::text(
            "Plugsent sites ({$sites->count()}):\n\n".$lines->implode("\n"),
        );
    }
}
