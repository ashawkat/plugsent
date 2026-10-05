<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves the connector plugin's latest GitHub release so the dashboard
 * can offer a direct download without storing the ZIP anywhere. The
 * release metadata is cached (the API is rate-limited) and every failure
 * degrades gracefully to the releases page.
 */
class ConnectorRelease
{
    public const CACHE_KEY = 'plugsent.connector-release';

    public const FAILURE_KEY = 'plugsent.connector-release-miss';

    /**
     * @return array{tag: string, name: string, zip: string}|null
     */
    public static function latest(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        // A recent lookup failure means GitHub is unreachable — don't
        // hammer it on every page render; fall back until the miss expires.
        if (Cache::has(self::FAILURE_KEY)) {
            return null;
        }

        try {
            $response = Http::accept('application/vnd.github+json')
                ->timeout(5)
                ->get('https://api.github.com/repos/'.config('plugsent.connector_repo').'/releases/latest');

            $release = $response->json();
            $zip = collect($release['assets'] ?? [])
                ->first(fn (array $asset): bool => str_ends_with((string) ($asset['name'] ?? ''), '.zip'))['browser_download_url'] ?? null;

            if (! $response->successful() || ! is_string($zip)) {
                Cache::put(self::FAILURE_KEY, true, now()->addMinutes(10));

                return null;
            }

            $resolved = [
                'tag' => (string) ($release['tag_name'] ?? ''),
                'name' => (string) ($release['name'] ?? $release['tag_name'] ?? ''),
                'zip' => $zip,
            ];

            Cache::put(self::CACHE_KEY, $resolved, now()->addHour());

            return $resolved;
        } catch (Throwable) {
            Cache::put(self::FAILURE_KEY, true, now()->addMinutes(10));

            return null;
        }
    }

    /**
     * The URL the download button should point at: the release ZIP when
     * resolvable, otherwise the releases page (never a dead link).
     */
    public static function downloadUrl(): string
    {
        $repo = config('plugsent.connector_repo');

        return self::latest()['zip'] ?? "https://github.com/{$repo}/releases/latest";
    }
}
