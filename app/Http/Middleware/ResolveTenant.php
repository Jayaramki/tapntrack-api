<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Populates TenantContext from the authenticated user. Must run AFTER auth:api
 * so the user is already resolved (calling $request->user() here returns the
 * cached guard user, no re-query).
 *
 * Also cross-checks the X-Tenant header (the slug the SPA is acting under)
 * against the user's tenant so a token from tenant A can't be replayed on
 * tenant B's URL.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $isPlatformAdmin = $user->role === 'super_admin';

            app(TenantContext::class)->set($user->tenant_id, $isPlatformAdmin);

            // Cross-check the acting tenant slug against the token's tenant.
            $headerSlug = $request->header('X-Tenant');
            if ($headerSlug && ! $isPlatformAdmin && $user->tenant && $user->tenant->slug !== $headerSlug) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant mismatch',
                ], 403);
            }
        }

        return $next($request);
    }
}
