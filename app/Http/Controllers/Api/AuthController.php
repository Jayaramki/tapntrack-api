<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RegisterRequest;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookProvisioner;
use App\Support\DeviceParser;
use App\Support\Passwords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

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

            $displayName = $data['owner_name'] ?? $data['username'];
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $displayName,
                'first_name' => $displayName,
                'last_name' => '',
                // Real email when supplied (enables email password reset);
                // otherwise a placeholder so the column stays populated.
                'email' => $data['email'] ?? ($data['username'].'@'.$data['slug'].'.tapntrack.local'),
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => 'tenant_admin',
                'book_id' => null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'is_deleted' => false,
                'permissions' => null,
            ]);

            return $user;
        });

        // Establish the session (SPA cookie auth).
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->stampSession($request);

        return $this->success(
            array_merge($this->formatUser($user), $this->sessionMeta($request)),
            'Registration successful',
            201
        );
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
            Log::warning('auth.login_failed', [
                'username' => $data['username'],
                'tenant' => $data['tenant_slug'] ?? null,
                'ip' => $request->ip(),
            ]);

            return $this->error('Invalid username or password', [], 401);
        }

        // Session-cookie login (Sanctum SPA); regenerate the id to prevent fixation.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->stampSession($request);

        Log::info('auth.login_success', ['user_id' => $user->id, 'username' => $user->username, 'ip' => $request->ip()]);

        return $this->success(
            array_merge($this->formatUser($user), $this->sessionMeta($request)),
            'Login successful'
        );
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
        // Invalidate the session entirely (id + CSRF token) on logout.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->success(null, 'Logout successful');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthenticated', [], 401);
        }

        return $this->success(
            array_merge($this->formatUser($user), $this->sessionMeta($request)),
            'User loaded'
        );
    }

    /** Re-authenticate (password) to reset the absolute-timeout clock; session stays. */
    public function reauth(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->error('Incorrect password', [], 422);
        }

        $this->stampSession($request); // resets auth_login_at (absolute clock)

        return $this->success($this->sessionMeta($request), 'Session extended');
    }

    /** Active sessions (devices) for the current user. */
    public function sessions(Request $request): JsonResponse
    {
        $currentId = $request->session()->getId();

        $rows = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        $sessions = $rows->map(function ($s) use ($currentId) {
            $device = DeviceParser::describe($s->user_agent);

            return [
                'id' => $s->id,
                'device' => $device['label'],
                'device_type' => $device['type'],
                'ip_address' => $s->ip_address,
                'last_active' => date(DATE_ATOM, (int) $s->last_activity),
                'is_current' => $s->id === $currentId,
            ];
        })->values();

        return $this->success($sessions, 'Sessions loaded');
    }

    /** Revoke one other device's session. */
    public function revokeSession(Request $request, string $id): JsonResponse
    {
        if ($id === $request->session()->getId()) {
            return $this->error('Use logout to end the current session', [], 422);
        }

        $deleted = DB::table('sessions')
            ->where('id', $id)
            ->where('user_id', $request->user()->id) // only your own sessions
            ->delete();

        if (! $deleted) {
            return $this->error('Session not found', [], 404);
        }

        return $this->success(null, 'Device logged out');
    }

    /** Revoke every session except the current device. */
    public function logoutOtherSessions(Request $request): JsonResponse
    {
        $count = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return $this->success(['revoked' => $count], 'Logged out of other devices');
    }

    /**
     * Email a single-use, time-limited reset link to an active account. Always
     * returns success so the response can't be used to enumerate which emails exist.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Log::info('auth.password_reset_requested', ['email' => $data['email'], 'ip' => $request->ip()]);

        Password::sendResetLink([
            'email' => $data['email'],
            'is_active' => true,
            'is_deleted' => false,
        ]);

        return $this->success(null, 'If that email is registered, a reset link has been sent.');
    }

    /** Consume the emailed token and set a new (strong) password. */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', Passwords::strong()],
        ]);

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'token' => $data['token'],
                'is_active' => true,
                'is_deleted' => false,
            ],
            function (User $user, string $password) {
                $user->password = $password; // 'hashed' cast applies
                $user->save();
                // Invalidate any existing sessions so a leaked session can't survive a reset.
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        );

        if ($status === Password::PasswordReset) {
            return $this->success(null, 'Your password has been reset. Please log in.');
        }

        return $this->error('This reset link is invalid or has expired.', [], 422);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', \App\Support\Passwords::strong()],
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($data['current_password'], $user->password)) {
            return $this->error('Current password is incorrect', [], 400);
        }

        $user->password = $data['new_password'];
        $user->save();

        return $this->success(null, 'Password changed successfully');
    }

    /** Stamp the session's login + last-seen timestamps (idle/absolute clocks). */
    private function stampSession(Request $request): void
    {
        $now = time();
        $request->session()->put('auth_login_at', $now);
        $request->session()->put('auth_last_seen', $now);
    }

    /** Session policy the SPA needs to drive its warning timers. */
    private function sessionMeta(Request $request): array
    {
        $loginAt = (int) $request->session()->get('auth_login_at', time());
        $absoluteMin = (int) config('session.absolute_timeout', 720);

        return [
            'idle_timeout_minutes' => (int) config('session.idle_timeout', 60),
            'absolute_expires_at' => date(DATE_ATOM, $loginAt + $absoluteMin * 60),
        ];
    }

    private function formatUser(User $user): array
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
            'hide_balance' => $this->agentBalanceHidden($user),
            'permissions' => $user->permissions ?: $this->permissionsForRole($user->role),
        ];
    }

    /**
     * Whether this user must NOT see loan balances: a field_agent whose book has
     * AGENT_SHOW_BALANCE off. The API also strips balances server-side; this flag
     * just lets the SPA hide the column/fields cleanly.
     */
    private function agentBalanceHidden(User $user): bool
    {
        if ($user->role !== 'field_agent' || ! $user->book_id) {
            return false;
        }

        $show = AppSetting::where('book_id', $user->book_id)
            ->where('key', 'AGENT_SHOW_BALANCE')->value('value');

        return $show === 'false' || $show === '0';
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
