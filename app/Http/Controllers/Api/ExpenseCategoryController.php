<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $categories = ExpenseCategory::where('book_id', $data['book_id'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->success($categories);
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        if ($deny = $this->denyBookAccess((string) $request->input('book_id'))) {
            return $deny;
        }

        $category = ExpenseCategory::create([
            'book_id' => $request->input('book_id'),
            'name' => $request->input('name'),
            'color' => $request->input('color', '#546E7A'),
            'is_active' => true,
        ]);

        return $this->success($category, 'Category created', 201);
    }

    public function update(UpdateExpenseCategoryRequest $request, string $id): JsonResponse
    {
        $category = ExpenseCategory::find($id);
        if (! $category) {
            return $this->error('Category not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $category->book_id)) {
            return $deny;
        }

        $category->update($request->only(['name', 'color', 'is_active']));

        return $this->success($category, 'Category updated');
    }

    /**
     * A category referenced by ANY expense cannot be deleted — only deactivated.
     * Otherwise it is removed.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = ExpenseCategory::find($id);
        if (! $category) {
            return $this->error('Category not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $category->book_id)) {
            return $deny;
        }

        $inUse = Expense::where('book_id', $category->book_id)
            ->where('category', $category->name)
            ->exists();

        if ($inUse) {
            return $this->error('This category is used by existing expenses and cannot be deleted. Deactivate it instead.', [], 409);
        }

        $category->delete();

        return $this->success(null, 'Category deleted');
    }
}
