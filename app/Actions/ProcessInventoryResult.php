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
                $rows[] = [
                    'site_id' => $site->getKey(),
                    'context' => $context,
                    'slug' => (string) ($item['slug'] ?? ''),
                    'name' => (string) ($item['name'] ?? $item['slug'] ?? ''),
                    'version' => $item['version'] ?? null,
                    'update_available' => (bool) ($item['update_available'] ?? false),
                    'update_version' => $item['update_version'] ?? null,
                    'active' => (bool) ($item['active'] ?? false),
                ];
            }
        }

        return $site->getConnection()->transaction(function () use ($site, $rows): int {
            InventoryItem::query()->where('site_id', $site->getKey())->delete();

            $timestamp = now();

            foreach ($rows as &$row) {
                $row['created_at'] = $timestamp;
                $row['updated_at'] = $timestamp;
            }

            InventoryItem::query()->insert($rows);

            return count($rows);
        });
    }
}
