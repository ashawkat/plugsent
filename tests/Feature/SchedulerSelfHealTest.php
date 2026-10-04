<?php

namespace Tests\Feature;

use App\Actions\EnsureScheduler;
use App\Console\Commands\InstallSchedulerCron;
use App\Support\Crontab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SchedulerSelfHealTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_schedule_entry_appends_the_line_once(): void
    {
        $crontab = new Crontab(
            reader: fn (): string => "0 3 * * 1 /usr/bin/backup\n",
            writer: function (string $content) use (&$written): void {
                $written = $content;
            },
        );

        $this->assertTrue($crontab->ensureScheduleEntry('/var/www/app'));
        $this->assertStringContainsString('php artisan schedule:run', $written);
        $this->assertStringContainsString('/var/www/app', $written);
        $this->assertStringContainsString('0 3 * * 1 /usr/bin/backup', $written);

        // Second run on the resulting crontab: already present, no change.
        $crontab = new Crontab(reader: fn (): string => $written, writer: fn () => throw new \RuntimeException('must not write'));
        $this->assertFalse($crontab->ensureScheduleEntry('/var/www/app'));
    }

    public function test_ensure_schedule_entry_recognizes_variants_of_the_entry(): void
    {
        // The xCloud-installed entry uses an unquoted path — still a match.
        $crontab = new Crontab(
            reader: fn (): string => "* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1\n",
            writer: fn () => throw new \RuntimeException('must not write'),
        );

        $this->assertFalse($crontab->ensureScheduleEntry('/var/www/app'));
    }

    public function test_self_heal_installs_the_cron_when_the_heartbeat_is_stale(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        Cache::put(EnsureScheduler::BEAT_KEY, now()->subMinutes(30), now()->addHour());

        $attempts = 0;
        $this->swap(Crontab::class, new Crontab(
            reader: fn (): string => '',
            writer: function () use (&$attempts): void {
                $attempts++;
            },
        ));

        app(EnsureScheduler::class)();

        $this->assertSame(1, $attempts);

        // The hourly throttle must prevent hammering on every poll.
        app(EnsureScheduler::class)();

        $this->assertSame(1, $attempts);
    }

    public function test_self_heal_skips_when_the_scheduler_is_alive(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        Cache::put(EnsureScheduler::BEAT_KEY, now(), now()->addHour());

        $this->swap(Crontab::class, new Crontab(
            reader: fn (): string => throw new \RuntimeException('must not read'),
            writer: fn () => throw new \RuntimeException('must not write'),
        ));

        app(EnsureScheduler::class)();

        $this->assertTrue(Cache::get(EnsureScheduler::BEAT_KEY)->gt(now()->subSeconds(5)));
    }

    public function test_self_heal_does_nothing_outside_production(): void
    {
        // The test suite runs in the "testing" environment, which the
        // action must treat as non-production.
        $this->swap(Crontab::class, new Crontab(
            reader: fn (): string => throw new \RuntimeException('must not read'),
            writer: fn () => throw new \RuntimeException('must not write'),
        ));

        app(EnsureScheduler::class)();

        $this->assertFalse(Cache::has(EnsureScheduler::HEAL_ATTEMPT_KEY));
    }

    public function test_artisan_command_installs_and_reports_idempotently(): void
    {
        $crontab = new Crontab(reader: fn (): string => '', writer: function (string $content) use (&$written): void {
            $written = $content;
        });
        $this->swap(Crontab::class, $crontab);

        $this->artisan(InstallSchedulerCron::class)
            ->expectsOutputToContain('installed')
            ->assertSuccessful();

        $crontab = new Crontab(reader: fn (): string => $written, writer: fn () => throw new \RuntimeException('must not write'));
        $this->swap(Crontab::class, $crontab);

        $this->artisan(InstallSchedulerCron::class)
            ->expectsOutputToContain('already present')
            ->assertSuccessful();
    }
}
