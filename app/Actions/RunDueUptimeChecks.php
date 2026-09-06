<?php

namespace App\Actions;

use App\Models\Site;

/**
 * Check every connected, enabled site whose interval has elapsed.
 *
 * Usually driven by the scheduler (`uptime:check`), but also piggybacked
 * onto connector check-ins so monitoring works even without a configured
 * server cron — the same trick as the inventory self-heal.
 */
class RunDueUptimeChecks
{
    public function __invoke(): int
    {
        $interval = (int) config('plugsent.uptime_interval_minutes', 5);

        $due = Site::query()
            ->where('status', 'connected')
            ->where('uptime_enabled', true)
            ->where(fn ($query) => $query
                ->whereNull('uptime_last_checked_at')
                ->orWhere('uptime_last_checked_at', '<=', now()->subMinutes($interval)))
            ->get();

        $check = app(CheckSiteUptime::class);

        foreach ($due as $site) {
            $check($site);
        }

        return $due->count();
    }
}
