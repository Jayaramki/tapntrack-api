<?php

namespace App\Support;

/**
 * Request-scoped holder for the tenant whose data the current request may touch
 * ("effective tenant"). Populated by the ResolveTenant middleware (after
 * auth:api), read by the BelongsToTenant global scope.
 *
 * Effective tenant by caller:
 *  - normal user            -> their own tenant_id.
 *  - super_admin (platform) -> NONE by default (sees no tenant data); only when
 *    impersonating does it become the target tenant.
 *
 * Decoupled from auth() so the scope never triggers guard resolution (querying
 * the User model during token auth would recurse). When nothing is set (login,
 * register, console, seeders) the scope does not filter.
 */
class TenantContext
{
    private ?string $tenantId = null;
    private bool $isPlatformAdmin = false;
    private bool $impersonating = false;
    private bool $resolved = false;

    public function set(?string $tenantId, bool $isPlatformAdmin, bool $impersonating = false): void
    {
        $this->tenantId = $tenantId;
        $this->isPlatformAdmin = $isPlatformAdmin;
        $this->impersonating = $impersonating;
        $this->resolved = true;
    }

    /** The tenant whose data is accessible this request (null = none). */
    public function effectiveTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->isPlatformAdmin;
    }

    public function isImpersonating(): bool
    {
        return $this->impersonating;
    }

    /** Apply the tenant filter? Yes whenever an effective tenant is set. */
    public function shouldScope(): bool
    {
        return $this->resolved && $this->tenantId !== null;
    }

    /**
     * Block all tenant data? A platform admin with no tenant selected sees no
     * tenant data (they manage tenants/billing, not borrower records). Distinct
     * from the unresolved case (login/console) which must NOT be blocked.
     */
    public function shouldBlockAll(): bool
    {
        return $this->resolved && $this->isPlatformAdmin && $this->tenantId === null;
    }
}
