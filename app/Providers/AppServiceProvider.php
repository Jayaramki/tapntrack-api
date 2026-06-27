<?php

namespace App\Providers;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-scoped: one shared instance per request so the ResolveTenant
        // middleware, the BelongsToTenant scope, and controllers all see the
        // same tenant. (The container is rebuilt per request.)
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Strict limiter for credential endpoints (brute-force / stuffing): keyed
        // by username + IP so one attacker can't grind a single account or spray.
        RateLimiter::for('auth', function (Request $request) {
            $key = Str::lower((string) $request->input('username', $request->input('email', ''))).'|'.$request->ip();

            return [Limit::perMinute(5)->by($key)];
        });

        // Sensible global API ceiling, keyed by the authenticated user or the IP.
        RateLimiter::for('api', function (Request $request) {
            return [Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())];
        });

        // Password-reset links point at the SPA reset page, not a backend route.
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $base = rtrim((string) config('app.frontend_url'), '/');

            return $base.'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }
}
