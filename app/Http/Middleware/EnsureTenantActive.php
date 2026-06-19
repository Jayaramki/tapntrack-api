<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only enforcement on lapse. When the effective tenant's subscription is
 * suspended or past_due, block all writes (POST/PUT/PATCH/DELETE) but allow
 * reads — data is never lost, just frozen until the account is back in good
 * standing. Must run AFTER ResolveTenant (effective tenant set).
 */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $tenantId = app(TenantContext::class)->effectiveTenantId();

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant && in_array($tenant->status, ['suspended', 'past_due'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your subscription is inactive — the workspace is read-only. Renew to make changes.',
                        'errors' => ['code' => 'subscription_inactive', 'status' => $tenant->status],
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
