<?php

namespace App\Providers;

use App\Support\MailSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        RateLimiter::for('connector', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                $request->header('X-Plugsent-Key') ?: $request->ip(),
            );
        });
    }
}
