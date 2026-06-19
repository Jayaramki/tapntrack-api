<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Services\PlanGate;
use Illuminate\Http\JsonResponse;

/**
 * The effective tenant's subscription: plan, status, limits and current usage.
 * Drives the frontend usage meters, upgrade prompts and read-only banner.
 */
class PlanController extends ApiController
{
    public function show(PlanGate $gate): JsonResponse
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            return $this->error('No tenant selected', [], 404);
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return $this->error('Tenant not found', [], 404);
        }

        return $this->success([
            'plan' => $tenant->plan,
            'status' => $tenant->status,
            'trial_ends_at' => $tenant->trial_ends_at,
            'limits' => $gate->limits($tenant),
            'usage' => $gate->usage($tenant),
        ]);
    }
}
