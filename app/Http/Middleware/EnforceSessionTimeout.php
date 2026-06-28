<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side backstop for the session policy:
 *  - idle:     log out after N minutes with no request.
 *  - absolute: log out after a hard cap on total session age, regardless of
 *              activity (the SPA prompts for a password re-auth before this,
 *              which re-stamps auth_login_at; a stolen cookie can't re-auth).
 * The SPA shows graceful warnings before either fires; this guarantees the
 * limits even if the front-end is bypassed.
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $now = time();
        $session = $request->session();
        $loginAt = (int) $session->get('auth_login_at', $now);
        $lastSeen = (int) $session->get('auth_last_seen', $now);

        $idle = (int) config('session.idle_timeout', 60) * 60;
        $absolute = (int) config('session.absolute_timeout', 720) * 60;

        $expired = ($now - $lastSeen) > $idle || ($now - $loginAt) > $absolute;

        if ($expired) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return response()->json([
                'success' => false,
                'message' => 'Session expired',
                'errors' => ['code' => 'session_expired'],
            ], 401);
        }

        // Slide the idle clock; absolute clock (auth_login_at) is untouched.
        $session->put('auth_last_seen', $now);

        return $next($request);
    }
}
