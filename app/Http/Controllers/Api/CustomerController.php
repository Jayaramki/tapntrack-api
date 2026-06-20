<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\DailyEntry;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $customers = Customer::where('book_id', $data['book_id'])
            ->when($request->filled('search'), function ($q) use ($data) {
                $term = '%'.$data['search'].'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when(($data['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($data['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->get();

        return $this->success($customers);
    }

    /**
     * GET /customers/lookup?book_id=&number= — quick-collection lookup. Returns
     * the customer plus their ACTIVE loans (brief) with today's entry per loan.
     * Balances are stripped for field agents when AGENT_SHOW_BALANCE is off.
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'number' => ['required', 'integer', 'min:1'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $customer = Customer::where('book_id', $data['book_id'])
            ->where('customer_number', $data['number'])
            ->first();

        if (! $customer) {
            return $this->error('No customer found with that number', [], 404);
        }

        $hide = $this->hideBalanceFor((string) $data['book_id']);
        $today = now()->toDateString();

        $loans = Loan::where('book_id', $data['book_id'])
            ->where('customer_id', $customer->id)
            ->where('is_deleted', false)
            ->whereNull('completed_date')
            ->orderBy('loan_number')
            ->get()
            ->map(function (Loan $l) use ($hide, $today) {
                $amount = (float) $l->loan_amount;
                $collected = (float) $l->total_collected;
                $entry = DailyEntry::where('loan_id', $l->id)->where('entry_date', $today)->first();

                $row = [
                    'id' => $l->id,
                    'loan_number' => $l->loan_number,
                    'loan_amount' => $amount,
                    'loan_type' => $l->loan_type,
                    'line' => $l->line,
                    'today_entry' => $entry
                        ? ['id' => $entry->id, 'amount' => (float) $entry->amount, 'mode' => $entry->mode]
                        : null,
                ];
                if (! $hide) {
                    $row['total_collected'] = $collected;
                    $row['remaining_balance'] = round($amount - $collected, 2);
                }

                return $row;
            })
            ->values();

        return $this->success([
            'customer' => [
                'id' => $customer->id,
                'customer_number' => $customer->customer_number,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'is_active' => (bool) $customer->is_active,
            ],
            'loans' => $loans,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->error('Customer not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $customer->book_id)) {
            return $deny;
        }

        return $this->success($customer);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        if ($deny = $this->denyBookAccess((string) $request->input('book_id'))) {
            return $deny;
        }

        $bookId = $request->input('book_id');
        // Manual override if given, else the next sequential number for this book.
        $number = $request->filled('customer_number')
            ? (int) $request->input('customer_number')
            : ((int) Customer::where('book_id', $bookId)->max('customer_number')) + 1;

        $customer = Customer::create([
            'book_id' => $bookId,
            'customer_number' => $number,
            'name' => $request->input('name'),
            'father_name' => $request->input('father_name'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'profession' => $request->input('profession'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->success($customer, 'Customer created successfully', 201);
    }

    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->error('Customer not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $customer->book_id)) {
            return $deny;
        }

        $customer->update($request->only([
            'customer_number', 'name', 'father_name', 'phone', 'address', 'profession', 'is_active',
        ]));

        return $this->success($customer, 'Customer updated successfully');
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->error('Customer not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $customer->book_id)) {
            return $deny;
        }

        $customer->update(['is_active' => ! $customer->is_active]);

        return $this->success($customer, 'Customer status updated');
    }
}
