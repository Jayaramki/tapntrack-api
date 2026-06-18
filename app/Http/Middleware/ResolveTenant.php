<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Populates TenantContext from the authenticated user. Must run AFTER auth:api
 * so the user is already resolved (calling $request->user() here returns the
 * cached guard user, no re-query).
 *
 *  - Normal user: effective tenant = their own tenant. The X-Tenant header (the
 *    slug the SPA acts under) is cross-checked so a token from tenant A can't be
 *    replayed on tenant B's URL.
 *  - Platform admin (super_admin): NO effective tenant by default — they see no
 *    borrower data. They may act on one tenant by sending X-Impersonate-Tenant
 *    (a slug); only then does that tenant become effective.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $context = app(TenantContext::class);

        if ($user->role === 'super_admin') {
            $slug = $request->header('X-Impersonate-Tenant');
            $tenant = $slug ? Tenant::where('slug', $slug)->where('is_deleted', false)->first() : null;
            // Impersonating a valid tenant -> that tenant is effective; otherwise
            // the platform admin has no tenant and sees no borrower data.
            $context->set($tenant?->id, isPlatformAdmin: true, impersonating: (bool) $tenant);

            return $next($request);
        }

        $context->set($user->tenant_id, isPlatformAdmin: false);

        // Cross-check the acting tenant slug against the token's tenant.
        $headerSlug = $request->header('X-Tenant');
        if ($headerSlug && $user->tenant && $user->tenant->slug !== $headerSlug) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant mismatch',
            ], 403);
        }

        return $next($request);
    }
}
