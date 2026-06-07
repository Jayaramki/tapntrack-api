<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            return $this->error('Invalid username or password', [], 401);
        }

        $user->api_token = Str::random(80);
        $user->save();

        return $this->success($this->formatUser($user, true), 'Login successful');
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
        ]);

        $user = User::where('username', $data['username'])->first();

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
        ]);

        $user = User::where('username', $data['username'])->first();

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
            'book_id' => $user->book_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'role' => $user->role,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'permissions' => $user->permissions ?? [],
            'token' => $includeToken ? $user->api_token : ($user->api_token ?? null),
        ];
    }
}
