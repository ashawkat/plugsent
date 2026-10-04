<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\UserSites;
use App\Models\InventoryItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List pending plugin, theme, and core updates across the caller\'s visible sites (optionally for one site or one context). Items marked excluded are skipped by update-site.')]
class ListPendingUpdates extends Tool
{
    private const CONTEXTS = [InventoryItem::CONTEXT_CORE, InventoryItem::CONTEXT_PLUGIN, InventoryItem::CONTEXT_THEME];

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()
                ->description('Restrict the list to one site (from list_sites). Omit for all sites.'),
            'context' => $schema->string()
                ->description('Restrict to one context: core, plugin, or theme. Omit for all.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $visibleSites = app(UserSites::class)($request->user());

        if ($request->get('site_id') !== null) {
            $requested = $visibleSites->firstWhere('id', (int) $request->get('site_id'));

            if ($requested === null) {
                return Response::text('No site with that id is visible to this account. Use list_sites to see the available sites.');
            }

            $visibleSites = collect([$requested]);
        }

        $context = $request->get('context');

        if ($context !== null && ! in_array($context, self::CONTEXTS, true)) {
            return Response::text('Unknown context "'.((string) $context).'". Use core, plugin, or theme.');
        }

        $rows = [];

        foreach ($visibleSites as $site) {
            $items = $site->inventory
                ->filter(fn (InventoryItem $item): bool => (bool) $item->update_available)
                ->when($context !== null, fn (Collection $items): Collection => $items->where('context', $context))
                ->sortBy([['context', 'asc'], ['name', 'asc']]);

            foreach ($items as $item) {
                $rows[] = [
                    'site' => $site,
                    'item' => $item,
                    'excluded' => $site->isExcludedFromUpdates($item->context, $item->slug),
                ];
            }
        }

        if ($rows === []) {
            return Response::text('No updates are pending across the visible sites.');
        }

        $bySite = collect($rows)->groupBy(fn (array $row): int => $row['site']->getKey());

        $blocks = $bySite->map(function (Collection $siteRows): string {
            $site = $siteRows->first()['site'];

            $items = $siteRows->map(fn (array $row): string => sprintf(
                '  - [%s] %s %s → %s%s',
                $row['item']->context,
                $row['item']->name,
                $row['item']->version !== '' && $row['item']->version !== null ? $row['item']->version : 'unknown',
                $row['item']->update_version !== '' && $row['item']->update_version !== null ? $row['item']->update_version : '?',
                $row['excluded'] ? ' (excluded — update-site skips it)' : '',
            ))->implode("\n");

            return sprintf(
                "[%d] %s (%s):\n%s",
                $site->getKey(),
                $site->name,
                $site->url,
                $items,
            );
        })->implode("\n");

        return Response::text(
            sprintf("Updates available (%d item(s)):\n\n", count($rows)).$blocks,
        );
    }
}
