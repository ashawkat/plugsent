<?php

use App\Http\Middleware\AuthenticateConnector;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::middleware('throttle:connector')
                ->group(__DIR__.'/../routes/connector.php');
            Route::middleware([])
                ->group(__DIR__.'/../routes/ai.php');
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'connector.auth' => AuthenticateConnector::class,
        ]);
        // There is no named `login` route in this app (Filament owns its own
        // auth flow), so the default "redirect guests to /login" behaviour
        // would fatal. Unauthenticated API-style callers get a 401 instead.
        $middleware->redirectGuestsTo(fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('mcp/*') || $request->expectsJson(),
        );
    })->create();
