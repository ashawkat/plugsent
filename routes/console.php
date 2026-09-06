<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The app's only scheduled work today: external uptime checks. The server
// cron runs `php artisan schedule:run` every minute; withoutOverlapping
// guards against a slow batch of checks overlapping the next minute.
Schedule::command('uptime:check')->everyMinute()->withoutOverlapping();

// The vulnerability feed is large and changes slowly — a weekly sync plus
// the manual "Sync now" button on the Settings page is plenty.
Schedule::command('vuln:sync')->weeklyOn(1, '3:00');
