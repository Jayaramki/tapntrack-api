<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Plan;
use App\Models\Scopes\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;

/**
 * Enforces per-plan limits (active loans, user seats, books) for a tenant.
 * Limits come from config/plans.php; counts bypass the tenant scope so this
 * works regardless of who is asking (tenant_admin or impersonating platform
 * admin).
 */
class PlanGate
{
    /**
     * Resolved limits for a tenant's plan. Reads the editable plans table (the
     * source of truth, managed from the admin UI); falls back to config when a
     * row is missing.
     */
    public function limits(Tenant $tenant): array
    {
        $plan = Plan::find($tenant->plan);

        if ($plan) {
            return [
                'plan' => $plan->code,
                'label' => $plan->label,
                'max_active_loans' => $plan->max_active_loans,
                'max_users' => $plan->max_users,
                'max_books' => $plan->max_books,
            ];
        }

        $cfg = config('plans.'.$tenant->plan) ?? config('plans.trial');

        return [
            'plan' => $tenant->plan,
            'label' => $cfg['label'] ?? ucfirst((string) $tenant->plan),
            'max_active_loans' => $cfg['max_active_loans'],
            'max_users' => $cfg['max_users'],
            'max_books' => $cfg['max_books'],
        ];
    }

    public function usage(Tenant $tenant): array
    {
        return [
            'active_loans' => $this->activeLoanCount($tenant),
            'users' => $this->userCount($tenant),
            'books' => $this->bookCount($tenant),
        ];
    }

    public function canAddLoan(Tenant $tenant): bool
    {
        return $this->under($this->limits($tenant)['max_active_loans'], $this->activeLoanCount($tenant));
    }

    public function canAddUser(Tenant $tenant): bool
    {
        return $this->under($this->limits($tenant)['max_users'], $this->userCount($tenant));
    }

    public function canAddBook(Tenant $tenant): bool
    {
        return $this->under($this->limits($tenant)['max_books'], $this->bookCount($tenant));
    }

    private function under(?int $max, int $current): bool
    {
        return $max === null || $current < $max;
    }

    private function bookCount(Tenant $tenant): int
    {
        return Book::withoutGlobalScope(BelongsToTenant::class)
            ->where('tenant_id', $tenant->id)->where('is_deleted', false)->count();
    }

    private function userCount(Tenant $tenant): int
    {
        return User::withoutGlobalScope(BelongsToTenant::class)
            ->where('tenant_id', $tenant->id)->where('is_deleted', false)->count();
    }

    private function activeLoanCount(Tenant $tenant): int
    {
        return Loan::query()
            ->join('books', 'books.id', '=', 'loans.book_id')
            ->where('books.tenant_id', $tenant->id)
            ->where('loans.is_deleted', false)
            ->whereNull('loans.completed_date')
            ->count();
    }
}
