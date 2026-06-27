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
        // Sanctum SPA auth: requests from SANCTUM_STATEFUL_DOMAINS authenticate via
        // the httpOnly session cookie + CSRF instead of a bearer token.
        $middleware->statefulApi();

        // JSON-only API + security headers on every API response.
        $middleware->api(prepend: [
            App\Http\Middleware\ForceJsonResponse::class,
            App\Http\Middleware\SecurityHeaders::class,
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
