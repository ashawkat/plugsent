<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Illuminate\Support\Facades\Route::middleware('throttle:connector')
                ->group(__DIR__.'/../routes/connector.php');
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'connector.auth' => App\Http\Middleware\AuthenticateConnector::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // TEMPORARY debug aid: mirror unhandled exceptions to a public file
        // so they can be read without server shell access. Remove after use.
        $exceptions->render(function (Throwable $e, Request $request) {
            @file_put_contents(
                public_path('debug-last-error.txt'),
                now()->toIso8601String().' '.$request->method().' '.$request->path()."\n"
                    .get_class($e).': '.$e->getMessage()."\n"
                    .$e->getTraceAsString()."\n\n",
                FILE_APPEND,
            );

            return null;
        });
    })->create();
