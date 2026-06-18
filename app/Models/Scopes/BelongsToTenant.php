<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant isolation for Book + User. Filters every query to the effective tenant.
 * A platform admin (super_admin) with no tenant selected sees NOTHING (they
 * manage tenants/billing, not borrower data) — access to a tenant's records is
 * only via explicit impersonation. Unauthenticated/console requests (login,
 * register, seeders) are not filtered. Reads TenantContext (never auth()) to
 * avoid recursing through the token guard during user resolution.
 */
class BelongsToTenant implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->shouldScope()) {
            $builder->where($model->qualifyColumn('tenant_id'), $context->effectiveTenantId());
            return;
        }

        if ($context->shouldBlockAll()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
