<?php

namespace App\Console\Commands;

use App\Support\Crontab;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plugsent:ensure-scheduler')]
#[Description('Install the Laravel scheduler cron entry (idempotent) so hourly scans, digests, and syncs run')]
class InstallSchedulerCron extends Command
{
    public function handle(Crontab $crontab): int
    {
        $changed = $crontab->ensureScheduleEntry(base_path());

        $this->info($changed
            ? 'Scheduler cron entry installed.'
            : 'Scheduler cron entry already present — nothing to do.');

        return self::SUCCESS;
    }
}
