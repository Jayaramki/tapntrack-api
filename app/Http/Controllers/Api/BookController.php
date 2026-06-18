<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\ExpenseCategory;
use App\Models\Line;
use App\Models\Tenant;
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

    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = DB::transaction(function () use ($request) {
            $book = Book::create([
                // New books belong to the creator's tenant (default tenant when
                // created by the platform owner in the single-tenant phase).
                'tenant_id' => $this->currentTenantId() ?? Tenant::DEFAULT_TENANT_ID,
                'name' => $request->input('name'),
                'owner_name' => $request->input('owner_name'),
                'is_active' => $request->boolean('is_active', true),
                'is_deleted' => false,
            ]);

            // Auto-seed default app settings for the new book
            $defaults = [
                'APP_NAME' => 'TapNTrack',
                'DAYS_TO_PAY' => '120',
                'INTEREST_PERCENTAGE' => '20',
            ];
            foreach ($defaults as $key => $value) {
                AppSetting::create([
                    'book_id' => $book->id,
                    'key' => $key,
                    'value' => $value,
                    'updated_by' => auth()->id(),
                ]);
            }

            // Auto-seed default expense categories for the new book
            $categories = [
                ['name' => 'Cheetu', 'color' => '#E65100'],
                ['name' => 'Vatti', 'color' => '#C62828'],
                ['name' => 'GPay', 'color' => '#1565C0'],
                ['name' => 'Other', 'color' => '#546E7A'],
            ];
            foreach ($categories as $cat) {
                ExpenseCategory::create([
                    'book_id' => $book->id,
                    'name' => $cat['name'],
                    'color' => $cat['color'],
                    'is_active' => true,
                ]);
            }

            // Auto-seed default collection lines for the new book
            foreach (['Line 1', 'Line 2', 'Line 3', 'Line 4', 'Line 5', 'Line 6'] as $lineName) {
                Line::create([
                    'book_id' => $book->id,
                    'name' => $lineName,
                    'color' => '#546E7A',
                    'is_active' => true,
                ]);
            }

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
