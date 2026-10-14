<?php

namespace App\Providers\Filament;

use App\Filament\Auth\MultiFactor\TotpAuthentication;
use App\Filament\Auth\Pages\Register;
use App\Models\Workspace;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->brandName('Plugsent')
            ->brandLogo(fn (): Htmlable => view('filament.partials.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.svg'))
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->multiFactorAuthentication(TotpAuthentication::make())
            ->revealablePasswords()
            ->tenant(Workspace::class, slugAttribute: 'slug')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): Htmlable => view('filament.partials.login-extras'),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): Htmlable => new HtmlString(
                    '<a class="plugsent-footer-support" href="https://ko-fi.com/adnanshawkat" target="_blank" rel="noopener noreferrer">'
                   .'Plugsent is free &amp; open source — support it on Ko-fi &hearts;</a>'
                ),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): Htmlable => new HtmlString(
                    '<link rel="preload" href="/fonts/google-sans-latin.woff2" as="font" type="font/woff2" crossorigin>'
                   .'<link rel="stylesheet" href="'.asset('css/plugsent.css').'?v='.filemtime(public_path('css/plugsent.css')).'">'
                ),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
