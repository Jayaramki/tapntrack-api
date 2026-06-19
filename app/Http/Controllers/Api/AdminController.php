<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Models\ImpersonationLog;
use App\Models\Loan;
use App\Models\Plan;
use App\Models\Scopes\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Platform-admin (super_admin) console. Operates ACROSS tenants and surfaces
 * only tenant *metadata* (plans, status, counts) — never borrower records. To
 * see a tenant's actual data the operator must impersonate (audited), which
 * grants scoped access through the normal tenant endpoints.
 */
class AdminController extends ApiController
{
    public function tenants(): JsonResponse
    {
        $tenants = Tenant::where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->get();

        // Aggregate counts in three grouped queries (scope bypassed — cross-tenant).
        $bookCounts = Book::withoutGlobalScope(BelongsToTenant::class)
            ->where('is_deleted', false)
            ->selectRaw('tenant_id, count(*) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id');

        $userCounts = User::withoutGlobalScope(BelongsToTenant::class)
            ->where('is_deleted', false)
            ->selectRaw('tenant_id, count(*) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id');

        $activeLoanCounts = Loan::query()
            ->join('books', 'books.id', '=', 'loans.book_id')
            ->where('loans.is_deleted', false)
            ->whereNull('loans.completed_date')
            ->selectRaw('books.tenant_id as tid, count(*) as c')
            ->groupBy('books.tenant_id')->pluck('c', 'tid');

        $data = $tenants->map(fn (Tenant $t) => $this->present($t, $bookCounts, $userCounts, $activeLoanCounts));

        return $this->success($data);
    }

    public function showTenant(string $id): JsonResponse
    {
        $tenant = Tenant::where('is_deleted', false)->find($id);
        if (! $tenant) {
            return $this->error('Tenant not found', [], 404);
        }

        $data = $this->present($tenant)->toArray();
        $data['recent_impersonations'] = ImpersonationLog::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')->limit(10)->get();

        return $this->success($data);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:trial,active,past_due,suspended'],
        ]);

        $tenant = Tenant::where('is_deleted', false)->find($id);
        if (! $tenant) {
            return $this->error('Tenant not found', [], 404);
        }

        $tenant->update(['status' => $data['status']]);

        return $this->success($this->present($tenant), 'Tenant status updated');
    }

    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'exists:plans,code'],
        ]);

        $tenant = Tenant::where('is_deleted', false)->find($id);
        if (! $tenant) {
            return $this->error('Tenant not found', [], 404);
        }

        // Assigning a paid plan implies the account is active.
        $tenant->update([
            'plan' => $data['plan'],
            'status' => $data['plan'] === 'trial' ? $tenant->status : 'active',
        ]);

        return $this->success($this->present($tenant), 'Tenant plan updated');
    }

    /** All subscription plans (tiers) with their editable limits. */
    public function plans(): JsonResponse
    {
        return $this->success(Plan::orderBy('sort_order')->get());
    }

    /** Edit a plan tier's label / limits (null limit = unlimited). */
    public function updatePlanLimits(Request $request, string $code): JsonResponse
    {
        $plan = Plan::find($code);
        if (! $plan) {
            return $this->error('Plan not found', [], 404);
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'max_active_loans' => ['present', 'nullable', 'integer', 'min:0'],
            'max_users' => ['present', 'nullable', 'integer', 'min:1'],
            'max_books' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        $plan->update($data);

        return $this->success($plan, 'Plan updated');
    }

    /**
     * Record an audited impersonation. Returns the slug the SPA should send as
     * X-Impersonate-Tenant to act as this tenant.
     */
    public function impersonate(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::where('is_deleted', false)->find($id);
        if (! $tenant) {
            return $this->error('Tenant not found', [], 404);
        }

        ImpersonationLog::create([
            'user_id' => auth()->id(),
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'action' => 'enter',
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        return $this->success(['slug' => $tenant->slug, 'name' => $tenant->name], 'Impersonation started');
    }

    public function stopImpersonate(Request $request): JsonResponse
    {
        $slug = $request->input('slug');
        $tenant = $slug ? Tenant::where('slug', $slug)->first() : null;

        if ($tenant) {
            ImpersonationLog::create([
                'user_id' => auth()->id(),
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'action' => 'exit',
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return $this->success(null, 'Impersonation ended');
    }

    private function present($tenant, $bookCounts = null, $userCounts = null, $activeLoanCounts = null)
    {
        return collect([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'owner_name' => $tenant->owner_name,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'status' => $tenant->status,
            'plan' => $tenant->plan,
            'plan_label' => config('plans.'.$tenant->plan.'.label', ucfirst((string) $tenant->plan)),
            'trial_ends_at' => $tenant->trial_ends_at,
            'created_at' => $tenant->created_at,
            'books_count' => (int) ($bookCounts[$tenant->id] ?? Book::withoutGlobalScope(BelongsToTenant::class)->where('tenant_id', $tenant->id)->where('is_deleted', false)->count()),
            'users_count' => (int) ($userCounts[$tenant->id] ?? User::withoutGlobalScope(BelongsToTenant::class)->where('tenant_id', $tenant->id)->where('is_deleted', false)->count()),
            'active_loans_count' => (int) ($activeLoanCounts[$tenant->id] ?? Loan::query()->join('books', 'books.id', '=', 'loans.book_id')->where('books.tenant_id', $tenant->id)->where('loans.is_deleted', false)->whereNull('loans.completed_date')->count()),
        ]);
    }
}
