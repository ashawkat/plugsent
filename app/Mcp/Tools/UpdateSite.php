<?php

namespace App\Mcp\Tools;

use App\Actions\EnqueueSiteCommand;
use App\Mcp\Support\UserSites;
use App\Models\InventoryItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Queue plugin, theme, or core updates on a site. Without slugs, every pending non-excluded item of that context is queued. Updates run through the site\'s connector, one at a time, with a restore point and automatic rollback when the connector supports safe updates.')]
class UpdateSite extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()
                ->required()
                ->description('The site id from list_sites.'),
            'context' => $schema->string()
                ->required()
                ->description('What to update: core, plugin, or theme.'),
            'slugs' => $schema->array()
                ->items($schema->string())
                ->description('Specific plugin/theme slugs (or "wordpress" for core) to update. Omit to update every pending item of the context.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $site = app(UserSites::class)($request->user())
            ->firstWhere('id', (int) $request->get('site_id'));

        if ($site === null) {
            return Response::text('No site with that id is visible to this account. Use list_sites to see the available sites.');
        }

        try {
            Gate::forUser($request->user())->authorize('update', $site);
        } catch (AuthorizationException) {
            return Response::text('You do not have permission to update this site.');
        }

        if (! $site->isConnected()) {
            return Response::text("{$site->name} is not connected right now — its updates cannot be queued until the site's connector is back online.");
        }

        $context = (string) $request->get('context');

        if (! in_array($context, [InventoryItem::CONTEXT_CORE, InventoryItem::CONTEXT_PLUGIN, InventoryItem::CONTEXT_THEME], true)) {
            return Response::text('Unknown context "'.$context.'". Use core, plugin, or theme.');
        }

        $pending = $site->inventory()
            ->where('update_available', true)
            ->where('context', $context)
            ->orderBy('name')
            ->get();

        $requestedSlugs = collect($request->get('slugs') ?? [])->map(fn ($slug): string => (string) $slug)->filter();

        if ($requestedSlugs->isNotEmpty()) {
            $pending = $pending->whereIn('slug', $requestedSlugs)->values();

            if ($pending->isEmpty()) {
                return Response::text("None of the requested items have a pending {$context} update on {$site->name}. Use list_pending_updates (site_id {$site->getKey()}) to see what is available.");
            }
        }

        if ($pending->isEmpty()) {
            return Response::text("Nothing to update: no {$context} updates are pending on {$site->name}.");
        }

        $eligible = $pending->reject(
            fn (InventoryItem $item): bool => $site->isExcludedFromUpdates($item->context, $item->slug),
        );

        $skipped = $pending->count() - $eligible->count();

        if ($eligible->isEmpty()) {
            return Response::text("Nothing to update: every pending {$context} item on {$site->name} is excluded from updates.");
        }

        $batchId = (string) Str::uuid();
        $safe = $context !== InventoryItem::CONTEXT_CORE && $site->supportsCommand('update.safe');

        foreach ($eligible as $item) {
            app(EnqueueSiteCommand::class)(
                $site,
                $safe ? 'update.safe' : 'update.run',
                ['context' => $item->context, 'slug' => $item->slug],
                $batchId,
            );
        }

        $summary = sprintf(
            'Queued %d %s update(s) for %s: %s.',
            $eligible->count(),
            $context,
            $site->name,
            $eligible->map(fn (InventoryItem $item): string => $item->name)->implode(', '),
        );

        if ($skipped > 0) {
            $summary .= " {$skipped} excluded item(s) skipped.";
        }

        $summary .= $safe
            ? ' They run one at a time on the site — restore point, smoke test, and automatic rollback included.'
            : ' They start within seconds — watch the site\'s update runs in the dashboard.';

        return Response::text($summary);
    }
}
