<?php

namespace App\Actions;

use App\Jobs\SecurityAlert;
use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\Vulnerability;

/**
 * Re-compute the vulnerability count of every inventory item by matching
 * installed versions against the synced feed. Uses version_compare
 * semantics — the same comparison WordPress itself uses.
 */
class MatchInventoryVulnerabilities
{
    public function __invoke(?Site $site = null): void
    {
        $items = InventoryItem::query()
            ->whereIn('context', [InventoryItem::CONTEXT_PLUGIN, InventoryItem::CONTEXT_THEME])
            ->when($site !== null, fn ($query) => $query->where('site_id', $site->getKey()))
            ->whereNotNull('version')
            ->get(['id', 'context', 'slug', 'version', 'site_id', 'vuln_count']);

        foreach ($items as $item) {
            $count = Vulnerability::query()
                ->where('software_type', $item->context)
                ->where('software_slug', $item->slug)
                ->get()
                ->filter(fn (Vulnerability $vulnerability) => $vulnerability->affectsVersion($item->version))
                ->count();

            InventoryItem::query()->whereKey($item->getKey())->update(['vuln_count' => $count]);

            // Exposure grew (new vulnerable software, or a newly published
            // vulnerability now covering an installed version) — alert the
            // workspace admins, throttled to once a day per site.
            if ($count > (int) $item->vuln_count) {
                SecurityAlert::dispatch($item->site_id);
            }
        }
    }
}
