<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'loan_number' => [
                'required', 'string', 'max:50',
                Rule::unique('loans', 'loan_number')->where('book_id', $this->input('book_id')),
            ],
            'loan_amount' => ['required', 'numeric', 'min:1'],
            'interest_amount' => ['required', 'numeric', 'min:0'],
            'loan_type' => ['required', 'in:daily,weekly,monthly'],
            'line' => ['required', 'in:line1,line2,line3,line4,line5,line6'],
            'issued_date' => ['required', 'date'],
        ];
    }
}
