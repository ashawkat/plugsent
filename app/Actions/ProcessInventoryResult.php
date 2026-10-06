<?php

namespace App\Actions;

use App\Models\InventoryItem;
use App\Models\Site;

class ProcessInventoryResult
{
    /**
     * Replace the site's inventory with a fresh snapshot.
     *
     * @param  array{core?: array|null, plugins?: array, themes?: array}  $inventory
     */
    public function __invoke(Site $site, array $inventory): int
    {
        $rows = [];

        foreach (['core', 'plugins', 'themes'] as $key) {
            $context = match ($key) {
                'core' => InventoryItem::CONTEXT_CORE,
                'plugins' => InventoryItem::CONTEXT_PLUGIN,
                'themes' => InventoryItem::CONTEXT_THEME,
            };

            foreach ($inventory[$key] ?? [] as $item) {
                $version = $item['version'] ?? null;
                $updateVersion = $item['update_version'] ?? null;
                $updateAvailable = (bool) ($item['update_available'] ?? false);

                // WordPress sometimes offers a "package refresh" of the
                // version that is already installed (e.g. after a partial
                // core update). An offered version that is not newer is not
                // an update — storing it as one makes every surface (badges,
                // counts, digest, MCP) claim an update that would change
                // nothing.
                if ($updateAvailable
                    && is_string($version) && $version !== ''
                    && is_string($updateVersion) && $updateVersion !== ''
                    && version_compare($updateVersion, $version, '<=')) {
                    $updateAvailable = false;
                }

                $rows[] = [
                    'site_id' => $site->getKey(),
                    'context' => $context,
                    'slug' => (string) ($item['slug'] ?? ''),
                    'name' => (string) ($item['name'] ?? $item['slug'] ?? ''),
                    'version' => $version,
                    'update_available' => $updateAvailable,
                    'update_version' => $updateVersion,
                    'active' => (bool) ($item['active'] ?? false),
                ];
            }
        }

        $count = $site->getConnection()->transaction(function () use ($site, $rows): int {
            InventoryItem::query()->where('site_id', $site->getKey())->delete();

            $timestamp = now();

            foreach ($rows as &$row) {
                $row['created_at'] = $timestamp;
                $row['updated_at'] = $timestamp;
            }

            InventoryItem::query()->insert($rows);

            return count($rows);
        });

        // Fresh inventory arrives unvetted; match it against the synced
        // vulnerability feed right away.
        app(MatchInventoryVulnerabilities::class)($site);

        // Fresh inventory is also the ground truth for "did that update
        // actually take?" — judge recent update runs against it.
        app(VerifyUpdateRuns::class)($site);

        return $count;
    }
}
