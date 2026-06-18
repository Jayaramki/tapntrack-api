<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Defense-in-depth tenant isolation for Book + User. Filters every query to the
 * current tenant unless the request is unauthenticated, run in console, or made
 * by the platform owner (super_admin). Reads TenantContext (never auth()) to
 * avoid recursing through the token guard during user resolution.
 */
class BelongsToTenant implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->shouldScope()) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $context->tenantId());
    }
}
