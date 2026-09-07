<?php

namespace App\Providers;

use App\Notifications\PasswordChangedNotification;
use App\Support\MailSettings;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MailSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(MailSettings $mailSettings): void
    {
        $mailSettings->apply();

        // A completed password reset is a password change — send the same
        // branded confirmation the profile page sends.
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            $event->user->notify(new PasswordChangedNotification());
        });

        RateLimiter::for('connector', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                $request->header('X-Plugsent-Key') ?: $request->ip(),
            );
        });
    }
}
