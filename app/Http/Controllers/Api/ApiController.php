<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Scopes\BelongsToTenant;
use App\Support\TenantContext;
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
     * Enforce tenant + book isolation against the *effective* tenant (a normal
     * user's own tenant, or the tenant a platform admin is impersonating):
     *   - no effective tenant (platform admin not impersonating) → denied.
     *   - the book must belong to the effective tenant (else 403).
     *   - tenant_admin / impersonating super_admin → any book in that tenant.
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

        $effectiveTenantId = $this->currentTenantId();

        // Platform admin with no tenant selected has no borrower-data access.
        if ($effectiveTenantId === null) {
            return $this->error('Access denied to this book', [], 403);
        }

        // The book must belong to the effective tenant. Query without the tenant
        // scope so a cross-tenant id returns an explicit 403, not a silent miss.
        $book = Book::withoutGlobalScope(BelongsToTenant::class)->find($bookId);
        if (! $book || (string) $book->tenant_id !== (string) $effectiveTenantId) {
            return $this->error('Access denied to this book', [], 403);
        }

        // Within the tenant, tenant_admin (and an impersonating super_admin) span
        // all books; book_admin / field_agent are pinned to their assigned book.
        $spansAllBooks = in_array($user->role, ['super_admin', 'tenant_admin'], true);
        if (! $spansAllBooks && (string) $user->book_id !== $bookId) {
            return $this->error('Access denied to this book', [], 403);
        }

        return null;
    }

    /**
     * The effective tenant id for this request: a normal user's own tenant, or
     * the tenant a platform admin is impersonating. Null when a platform admin
     * is not impersonating (or unauthenticated).
     */
    protected function currentTenantId(): ?string
    {
        return app(TenantContext::class)->effectiveTenantId();
    }
}
