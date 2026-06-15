<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $expenses = Expense::where('book_id', $data['book_id'])
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn (Expense $e) => $this->present($e));

        return $this->success($expenses);
    }

    public function show(string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (! $expense) {
            return $this->error('Expense not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $expense->book_id)) {
            return $deny;
        }

        return $this->success($this->present($expense));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        if ($deny = $this->denyBookAccess((string) $request->input('book_id'))) {
            return $deny;
        }

        $expense = Expense::create([
            'book_id' => $request->input('book_id'),
            'expense_date' => $request->input('expense_date'),
            'description' => $request->input('description'),
            'category' => $request->input('category'),
            'amount' => $request->input('amount'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->success($this->present($expense), 'Expense recorded', 201);
    }

    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (! $expense) {
            return $this->error('Expense not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $expense->book_id)) {
            return $deny;
        }

        $expense->update($request->only([
            'expense_date', 'description', 'category', 'amount', 'is_active',
        ]));

        return $this->success($this->present($expense), 'Expense updated');
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (! $expense) {
            return $this->error('Expense not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $expense->book_id)) {
            return $deny;
        }

        $expense->update(['is_active' => ! $expense->is_active]);

        return $this->success($this->present($expense), 'Expense status updated');
    }

    private function present(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'book_id' => (string) $expense->book_id,
            'expense_date' => $expense->expense_date,
            'description' => $expense->description,
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'is_active' => (bool) $expense->is_active,
            'created_at' => $expense->created_at,
            'updated_at' => $expense->updated_at,
        ];
    }
}
