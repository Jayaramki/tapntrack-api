<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Tenant;
use App\Services\BookProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BookController extends ApiController
{
    public function index(): JsonResponse
    {
        $books = Book::withCount('users')
            ->where('is_deleted', false)
            ->orderBy('name')
            ->get();

        return $this->success($books);
    }

    public function show(string $id): JsonResponse
    {
        $book = Book::withCount('users')->where('is_deleted', false)->find($id);

        if (! $book) {
            return $this->error('Book not found', [], 404);
        }

        return $this->success($book);
    }

    public function store(StoreBookRequest $request, BookProvisioner $provisioner): JsonResponse
    {
        $book = DB::transaction(function () use ($request, $provisioner) {
            $book = Book::create([
                // New books belong to the creator's tenant (default tenant when
                // created by the platform owner in the single-tenant phase).
                'tenant_id' => $this->currentTenantId() ?? Tenant::DEFAULT_TENANT_ID,
                'name' => $request->input('name'),
                'owner_name' => $request->input('owner_name'),
                'is_active' => $request->boolean('is_active', true),
                'is_deleted' => false,
            ]);

            $provisioner->seedDefaults($book, updatedBy: auth()->id());

            return $book;
        });

        return $this->success($book, 'Book created successfully', 201);
    }

    public function update(UpdateBookRequest $request, string $id): JsonResponse
    {
        $book = Book::where('is_deleted', false)->find($id);

        if (! $book) {
            return $this->error('Book not found', [], 404);
        }

        $book->update($request->only(['name', 'owner_name', 'is_active']));

        return $this->success($book, 'Book updated successfully');
    }

    public function toggleActive(string $id): JsonResponse
    {
        $book = Book::where('is_deleted', false)->find($id);

        if (! $book) {
            return $this->error('Book not found', [], 404);
        }

        $book->update(['is_active' => ! $book->is_active]);

        return $this->success($book, 'Book status updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $book = Book::where('is_deleted', false)->find($id);

        if (! $book) {
            return $this->error('Book not found', [], 404);
        }

        $book->update(['is_deleted' => true]);

        return $this->success(null, 'Book deleted successfully');
    }
}
