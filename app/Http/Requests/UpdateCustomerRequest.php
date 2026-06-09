<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = (int) $this->route('id');
        $bookId = $this->input('book_id');

        return [
            'book_id' => ['sometimes', 'required', 'integer', 'exists:books,id'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'phone' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('customers', 'phone')
                    ->where('book_id', $bookId)
                    ->ignore($customerId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
