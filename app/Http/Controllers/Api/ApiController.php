<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    protected function error(string $message = 'Error', array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Enforce book-level isolation. super_admin can access any book; every
     * other role may only touch their own book. Returns an error response to
     * short-circuit on denial, or null when access is allowed.
     *
     * Usage: if ($deny = $this->denyBookAccess($bookId)) return $deny;
     */
    protected function denyBookAccess(int $bookId): ?JsonResponse
    {
        $user = auth()->user();

        if ($user && $user->role !== 'super_admin' && (int) $user->book_id !== $bookId) {
            return $this->error('Access denied to this book', [], 403);
        }

        return null;
    }
}
