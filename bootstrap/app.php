<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // JSON-only API: always render JSON (no 302 redirects on validation/auth).
        $middleware->api(prepend: [
            App\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'role' => App\Http\Middleware\CheckRole::class,
            'tenant' => App\Http\Middleware\ResolveTenant::class,
            'active' => App\Http\Middleware\EnsureTenantActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
