<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $current = auth()->user();

        if ($current->role === 'field_agent') {
            return $this->error('You are not allowed to manage users', [], 403);
        }

        $data = $request->validate([
            'book_id' => ['nullable', 'uuid', 'exists:books,id'],
        ]);

        $query = User::where('is_deleted', false);

        if ($current->role === 'book_admin') {
            // Only their own book's field agents
            $query->where('book_id', $current->book_id)->where('role', 'field_agent');
        } elseif (! empty($data['book_id'])) {
            $query->where('book_id', $data['book_id']);
        }

        $users = $query->orderBy('first_name')->get()->map(fn (User $u) => $this->present($u));

        return $this->success($users);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::where('is_deleted', false)->find($id);
        if (! $user) {
            return $this->error('User not found', [], 404);
        }
        if ($deny = $this->denyUserManagement($user->role, $user->book_id)) {
            return $deny;
        }

        return $this->success($this->present($user));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        if ($deny = $this->denyUserManagement($request->input('role'), $request->input('book_id'))) {
            return $deny;
        }

        $first = $request->input('first_name');
        $last = $request->input('last_name');

        $user = User::create([
            // New users inherit the creator's tenant (tenant-level identity).
            'tenant_id' => $this->currentTenantId() ?? \App\Models\Tenant::DEFAULT_TENANT_ID,
            'name' => trim("$first $last"),
            'email' => $request->input('username').'@tapntrack.local',
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'first_name' => $first,
            'last_name' => $last,
            'role' => $request->input('role'),
            'book_id' => in_array($request->input('role'), ['super_admin', 'tenant_admin'], true) ? null : $request->input('book_id'),
            'phone' => $request->input('phone'),
            'security_question' => $request->input('security_question'),
            'security_answer' => $request->input('security_answer'),
            'is_active' => true,
            'is_deleted' => false,
            'permissions' => null,
        ]);

        return $this->success($this->present($user), 'User created successfully', 201);
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = User::where('is_deleted', false)->find($id);
        if (! $user) {
            return $this->error('User not found', [], 404);
        }

        // Must be allowed to manage both the current and the resulting role/book.
        if ($deny = $this->denyUserManagement($user->role, $user->book_id)) {
            return $deny;
        }
        $newRole = $request->input('role', $user->role);
        $newBook = in_array($newRole, ['super_admin', 'tenant_admin'], true) ? null : $request->input('book_id', $user->book_id);
        if ($deny = $this->denyUserManagement($newRole, $newBook)) {
            return $deny;
        }

        $user->fill($request->only([
            'username', 'first_name', 'last_name', 'role', 'phone',
            'security_question', 'security_answer',
        ]));
        $user->book_id = $newBook;
        if ($request->filled('first_name') || $request->filled('last_name')) {
            $user->name = trim($user->first_name.' '.$user->last_name);
        }
        $user->save();

        return $this->success($this->present($user), 'User updated successfully');
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $user = User::where('is_deleted', false)->find($id);
        if (! $user) {
            return $this->error('User not found', [], 404);
        }
        if ($deny = $this->denyUserManagement($user->role, $user->book_id)) {
            return $deny;
        }
        if ((string) $user->id === (string) auth()->id()) {
            return $this->error('You cannot deactivate your own account', [], 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return $this->success($this->present($user), 'User status updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::where('is_deleted', false)->find($id);
        if (! $user) {
            return $this->error('User not found', [], 404);
        }
        if ($deny = $this->denyUserManagement($user->role, $user->book_id)) {
            return $deny;
        }
        if ((string) $user->id === (string) auth()->id()) {
            return $this->error('You cannot delete your own account', [], 422);
        }

        $user->update(['is_deleted' => true, 'api_token' => null]);

        return $this->success(null, 'User deleted successfully');
    }

    /**
     * super_admin manages anyone; tenant_admin manages any non-platform user
     * within their tenant; book_admin may only manage field_agents in their own
     * book; field_agent may manage no one.
     */
    private function denyUserManagement(?string $targetRole, ?string $targetBookId): ?JsonResponse
    {
        $current = auth()->user();

        if ($current->role === 'super_admin') {
            return null;
        }

        if ($current->role === 'tenant_admin') {
            // May manage tenant_admin / book_admin / field_agent, never the
            // platform owner. The book (when set) must be in this tenant.
            if ($targetRole === 'super_admin') {
                return $this->error('You cannot manage platform administrators', [], 403);
            }
            if ($targetBookId) {
                $book = \App\Models\Book::withoutGlobalScope(\App\Models\Scopes\BelongsToTenant::class)->find($targetBookId);
                if (! $book || (string) $book->tenant_id !== (string) $current->tenant_id) {
                    return $this->error('Access denied to this book', [], 403);
                }
            }

            return null;
        }

        if ($current->role === 'book_admin') {
            if ($targetRole !== 'field_agent' || (string) $targetBookId !== (string) $current->book_id) {
                return $this->error('You can only manage field agents in your own book', [], 403);
            }

            return null;
        }

        return $this->error('You are not allowed to manage users', [], 403);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'book_id' => $user->book_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'role' => $user->role,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
        ];
    }
}
