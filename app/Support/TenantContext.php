<?php

namespace App\Support;

/**
 * Request-scoped holder for the authenticated user's tenant. Populated by the
 * ResolveTenant middleware (which runs AFTER auth:api), read by the
 * BelongsToTenant global scope.
 *
 * Deliberately decoupled from auth() so the scope never triggers guard
 * resolution — querying the User model during token auth would otherwise
 * recurse. When nothing is set (login, register, console, seeders) the scope
 * simply does not filter.
 */
class TenantContext
{
    private ?string $tenantId = null;
    private bool $isPlatformAdmin = false;
    private bool $resolved = false;

    public function set(?string $tenantId, bool $isPlatformAdmin): void
    {
        $this->tenantId = $tenantId;
        $this->isPlatformAdmin = $isPlatformAdmin;
        $this->resolved = true;
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->isPlatformAdmin;
    }

    /**
     * Should the BelongsToTenant scope apply? Only when we have a resolved,
     * non-platform tenant. Platform admins (super_admin) span all tenants.
     */
    public function shouldScope(): bool
    {
        return $this->resolved && ! $this->isPlatformAdmin && $this->tenantId !== null;
    }
}
