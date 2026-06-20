<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            // Optional manual override; otherwise auto-assigned per book.
            'customer_number' => [
                'nullable', 'integer', 'min:1',
                Rule::unique('customers', 'customer_number')->where('book_id', $this->input('book_id')),
            ],
            'name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'phone' => [
                'required', 'string', 'max:20',
                Rule::unique('customers', 'phone')->where('book_id', $this->input('book_id')),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
