<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        if ($deny = $this->denyBookAccess((int) $data['book_id'])) {
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

    public function show(int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->error('Customer not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((int) $customer->book_id)) {
            return $deny;
        }

        return $this->success($customer);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        if ($deny = $this->denyBookAccess((int) $request->input('book_id'))) {
            return $deny;
        }

        $customer = Customer::create([
            'book_id' => $request->input('book_id'),
            'name' => $request->input('name'),
            'father_name' => $request->input('father_name'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'profession' => $request->input('profession'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->success($customer, 'Customer created successfully', 201);
    }

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->error('Customer not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((int) $customer->book_id)) {
            return $deny;
        }

        $customer->update($request->only([
            'name', 'father_name', 'phone', 'address', 'profession', 'is_active',
        ]));

        return $this->success($customer, 'Customer updated successfully');
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->error('Customer not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((int) $customer->book_id)) {
            return $deny;
        }

        $customer->update(['is_active' => ! $customer->is_active]);

        return $this->success($customer, 'Customer status updated');
    }
}
