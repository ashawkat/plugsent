<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Notifications\SiteDownNotification;
use App\Notifications\SiteRecoveredNotification;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class UptimeCheck extends Command
{
    protected $signature = 'uptime:check';

    protected $description = 'Check every due site\'s public URL, open/close downtime incidents, and notify the workspace';

    /**
     * Two consecutive failures open an incident — a single network blip is
     * not an outage. One healthy check closes it.
     */
    private const FAILURES_BEFORE_INCIDENT = 2;

    public function handle(): int
    {
        $interval = (int) config('plugsent.uptime_interval_minutes', 5);

        $due = Site::query()
            ->where('status', 'connected')
            ->where('uptime_enabled', true)
            ->where(fn ($query) => $query
                ->whereNull('uptime_last_checked_at')
                ->orWhere('uptime_last_checked_at', '<=', now()->subMinutes($interval)))
            ->get();

        foreach ($due as $site) {
            $this->checkSite($site);
        }

        $this->info($due->count().' site(s) checked.');

        return self::SUCCESS;
    }

    private function checkSite(Site $site): void
    {
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(10)->get($site->url);
            $statusCode = $response->status();
            $error = null;
        } catch (ConnectionException $e) {
            $statusCode = null;
            $error = str($e->getMessage())->limit(200)->toString();
        }

        $site->forceFill([
            'uptime_last_checked_at' => now(),
            'uptime_last_status_code' => $statusCode,
            'uptime_last_response_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            'uptime_last_error' => $error,
        ])->save();

        $healthy = $error === null
            && (($statusCode >= 200 && $statusCode < 400) || $statusCode === 401 || $statusCode === 403);

        if ($healthy) {
            $this->markUp($site);
        } else {
            $this->markDown($site, $statusCode, $error);
        }
    }

    private function markUp(Site $site): void
    {
        $site->forceFill([
            'uptime_status' => Site::UPTIME_UP,
            'uptime_consecutive_failures' => 0,
        ])->save();

        $incident = $site->activeIncident();

        if ($incident === null) {
            return;
        }

        $incident->forceFill(['ended_at' => now()])->save();

        foreach ($this->recipients($site) as $user) {
            $user->notify(new SiteRecoveredNotification($site, $incident));
        }
    }

    private function markDown(Site $site, ?int $statusCode, ?string $error): void
    {
        $failures = $site->uptime_consecutive_failures + 1;

        $site->forceFill([
            'uptime_status' => Site::UPTIME_DOWN,
            'uptime_consecutive_failures' => $failures,
        ])->save();

        $incident = $site->activeIncident();

        if ($incident !== null) {
            $incident->forceFill([
                'last_status_code' => $statusCode,
                'last_error' => $error,
                'failure_count' => $failures,
            ])->save();

            return;
        }

        if ($failures < self::FAILURES_BEFORE_INCIDENT) {
            return;
        }

        $incident = $site->uptimeIncidents()->create([
            'started_at' => now(),
            'last_status_code' => $statusCode,
            'last_error' => $error,
            'failure_count' => $failures,
            'down_notified' => true,
        ]);

        foreach ($this->recipients($site) as $user) {
            $user->notify(new SiteDownNotification($site, $incident));
        }
    }

    /**
     * Workspace owners and admins own the pager for their sites.
     */
    private function recipients(Site $site): Collection
    {
        return $site->workspace->users()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();
    }
}
