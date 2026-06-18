<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RegisterRequest;
use App\Models\Book;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    /**
     * Self-signup: create a tenant (free trial) with its first tenant_admin and
     * a starter book (default settings/categories/lines), then log them in.
     */
    public function register(RegisterRequest $request, BookProvisioner $provisioner): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $provisioner) {
            $tenant = Tenant::create([
                'slug' => $data['slug'],
                'name' => $data['tenant_name'],
                'owner_name' => $data['owner_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'is_deleted' => false,
            ]);

            $book = Book::create([
                'tenant_id' => $tenant->id,
                'name' => $data['tenant_name'],
                'owner_name' => $data['owner_name'] ?? null,
                'is_active' => true,
                'is_deleted' => false,
            ]);
            $provisioner->seedDefaults($book, appName: $data['tenant_name']);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['username'],
                'email' => $data['username'].'@'.$data['slug'].'.tapntrack.local',
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => 'tenant_admin',
                'book_id' => null,
                'phone' => $data['phone'] ?? null,
                'security_question' => $data['security_question'] ?? null,
                'security_answer' => $data['security_answer'] ?? null,
                'is_active' => true,
                'is_deleted' => false,
                'permissions' => null,
            ]);

            $user->api_token = Str::random(80);
            $user->save();

            return $user;
        });

        return $this->success($this->formatUser($user, true), 'Registration successful', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'tenant_slug' => ['nullable', 'string'],
        ]);

        $user = $this->resolveUser($data['username'], $data['tenant_slug'] ?? null);

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            return $this->error('Invalid username or password', [], 401);
        }

        $user->api_token = Str::random(80);
        $user->save();

        return $this->success($this->formatUser($user, true), 'Login successful');
    }

    /**
     * Resolve a user by username, scoped to a tenant when a slug is supplied.
     * No auth context exists yet at login, so the BelongsToTenant scope is
     * inert here — tenant scoping is applied explicitly. When no slug is given
     * (legacy clients, single-tenant), falls back to a global lookup.
     */
    private function resolveUser(string $username, ?string $tenantSlug): ?User
    {
        $query = User::where('username', $username)->where('is_deleted', false);

        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->where('is_deleted', false)->first();
            if (! $tenant) {
                return null;
            }
            $query->where('tenant_id', $tenant->id);
        }

        return $query->first();
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->api_token = null;
            $user->save();
        }

        return $this->success(null, 'Logout successful');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthenticated', [], 401);
        }

        return $this->success($this->formatUser($user), 'User loaded');
    }

    public function getSecurityQuestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'tenant_slug' => ['nullable', 'string'],
        ]);

        $user = $this->resolveUser($data['username'], $data['tenant_slug'] ?? null);

        if (! $user) {
            return $this->error('Username not found', [], 404);
        }

        return $this->success(['question' => $user->security_question], 'Security question loaded');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'answer' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6'],
            'tenant_slug' => ['nullable', 'string'],
        ]);

        $user = $this->resolveUser($data['username'], $data['tenant_slug'] ?? null);

        if (! $user || ! $user->security_answer || strtolower(trim($user->security_answer)) !== strtolower(trim($data['answer']))) {
            return $this->error('Security answer is incorrect', [], 400);
        }

        $user->password = $data['new_password'];
        $user->save();

        return $this->success(null, 'Password reset successful');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6'],
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($data['current_password'], $user->password)) {
            return $this->error('Current password is incorrect', [], 400);
        }

        $user->password = $data['new_password'];
        $user->save();

        return $this->success(null, 'Password changed successfully');
    }

    private function formatUser(User $user, bool $includeToken = false): array
    {
        return [
            'id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'tenant_slug' => $user->tenant?->slug,
            'book_id' => $user->book_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'role' => $user->role,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'permissions' => $user->permissions ?: $this->permissionsForRole($user->role),
            'token' => $includeToken ? $user->api_token : ($user->api_token ?? null),
        ];
    }

    /**
     * Map a role to its permission list. Mirrors the strings the Angular
     * route guards and menu check against. Used when a user has no explicit
     * permissions stored on their record.
     */
    private function permissionsForRole(?string $role): array
    {
        $all = [
            'manage-books', 'manage-users', 'manage-customers', 'view-loans',
            'create-loans', 'edit-loans', 'delete-loans', 'archive-loans',
            'record-collection', 'view-pending-loans', 'view-day-summary',
            'view-ledger', 'manage-expenses', 'view-reports', 'manage-settings',
            'view-dashboard',
        ];

        return match ($role) {
            // Platform owner: every book-level power plus tenant administration.
            'super_admin' => array_merge($all, ['manage-tenants', 'manage-billing']),
            // Tenant owner: all book-level powers within their tenant + billing.
            'tenant_admin' => array_merge($all, ['manage-billing']),
            'book_admin' => array_values(array_diff($all, ['manage-books'])),
            'field_agent' => ['view-loans', 'record-collection', 'view-pending-loans'],
            default => [],
        };
    }
}
