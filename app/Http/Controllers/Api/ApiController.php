<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Scopes\BelongsToTenant;
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
     * Enforce tenant + book isolation. Layers:
     *   - super_admin (platform owner) → any book in any tenant.
     *   - the book must belong to the user's tenant (else 403).
     *   - tenant_admin → any book within their tenant.
     *   - book_admin / field_agent → only their assigned book.
     *
     * Returns an error response to short-circuit on denial, or null when
     * access is allowed.
     *
     * Usage: if ($deny = $this->denyBookAccess($bookId)) return $deny;
     */
    protected function denyBookAccess(string $bookId): ?JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        // Platform owner spans every tenant and book.
        if ($user->role === 'super_admin') {
            return null;
        }

        // The book must belong to the user's tenant. Query without the tenant
        // scope so a cross-tenant id returns an explicit 403, not a silent miss.
        $book = Book::withoutGlobalScope(BelongsToTenant::class)->find($bookId);
        if (! $book || (string) $book->tenant_id !== (string) $user->tenant_id) {
            return $this->error('Access denied to this book', [], 403);
        }

        // Within the tenant, only tenant_admin spans all books; others are
        // pinned to their assigned book.
        if ($user->role !== 'tenant_admin' && (string) $user->book_id !== $bookId) {
            return $this->error('Access denied to this book', [], 403);
        }

        return null;
    }

    /**
     * The current authenticated user's tenant id (null for the platform owner
     * or unauthenticated requests).
     */
    protected function currentTenantId(): ?string
    {
        return auth()->user()?->tenant_id;
    }
}
