<?php

namespace App\Actions;

use App\Support\Crontab;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A dead scheduler silently kills every part of the product that depends
 * on schedules: hourly inventory refreshes, update emails, digests, vuln
 * syncs. Instead of asking operators to install a cron by hand, the app
 * notices the missing heartbeat (checked on every connector poll) and
 * installs its own `schedule:run` entry — self-healing on fresh installs
 * and on servers that lost the entry.
 */
class EnsureScheduler
{
    public const BEAT_KEY = 'plugsent.scheduler-beat';

    public const HEAL_ATTEMPT_KEY = 'plugsent.scheduler-heal-attempt';

    public function __invoke(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $beat = Cache::get(self::BEAT_KEY);

        if ($beat !== null && $beat->gt(now()->subMinutes(5))) {
            return; // scheduler is alive
        }

        // One attempt per hour: a broken crontab should not turn into a
        // per-poll hammering loop.
        if (Cache::has(self::HEAL_ATTEMPT_KEY)) {
            return;
        }

        Cache::put(self::HEAL_ATTEMPT_KEY, now(), now()->addHour());

        try {
            $changed = app(Crontab::class)->ensureScheduleEntry(base_path());

            if ($changed) {
                Log::info('Scheduler heartbeat was stale — installed the schedule:run cron entry.');
            }
        } catch (Throwable $error) {
            Log::warning('Could not self-install the scheduler cron: '.$error->getMessage());
        }
    }
}
