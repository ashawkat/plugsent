<?php

namespace App\Mcp\Tools;

use App\Actions\EnqueueSiteCommand;
use App\Mcp\Support\TokenAccess;
use App\Mcp\Support\UserSites;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Queue a fresh inventory rescan (core, plugins, themes) for one site or, without site_id, for every connected site the caller may update — the MCP counterpart of the dashboard\'s "refresh inventory" button. Sites report the fresh inventory on their next connector check-in.')]
class RescanInventory extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()
                ->description('Rescan one site (from list_sites). Omit to rescan all connected sites you may update.'),
        ];
    }

    public function handle(Request $request): Response
    {
        if (! TokenAccess::canWrite($request)) {
            return Response::text('This token is read-only — it can list sites, statuses, and pending updates, but cannot trigger rescans. Generate a token with write access from My account → MCP access.');
        }

        $user = $request->user();
        $sites = app(UserSites::class)($user);
        $single = $request->get('site_id') !== null;

        if ($single) {
            $site = $sites->firstWhere('id', (int) $request->get('site_id'));

            if ($site === null) {
                return Response::text('No site with that id is visible to this account. Use list_sites to see the available sites.');
            }

            try {
                Gate::forUser($user)->authorize('update', $site);
            } catch (AuthorizationException) {
                return Response::text('You do not have permission to rescan this site.');
            }

            $sites = collect([$site]);
        }

        $queued = collect();

        foreach ($sites as $site) {
            if (! $site->isConnected() || ! $user->can('update', $site)) {
                continue;
            }

            app(EnqueueSiteCommand::class)($site, 'inventory.get', null, null, 'mcp', TokenAccess::actor($request));

            $queued->push($site);
        }

        if ($queued->isEmpty()) {
            return Response::text($single
                ? $site->name.' is not connected right now — it cannot be rescanned until its connector is back online.'
                : 'Nothing to rescan: no connected sites are available to you right now.');
        }

        return Response::text(sprintf(
            'Queued %d inventory rescan%s: %s. Sites report fresh inventory on their next connector check-in.',
            $queued->count(),
            $queued->count() === 1 ? '' : 's',
            $queued->map(fn ($site): string => '['.$site->getKey().'] '.$site->name)->implode(', '),
        ));
    }
}
