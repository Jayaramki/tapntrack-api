<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $loanId = $this->route('id');
        $bookId = $this->input('book_id', $this->route('book_id'));

        return [
            'book_id' => ['sometimes', 'uuid', 'exists:books,id'],
            'customer_id' => ['sometimes', 'uuid', 'exists:customers,id'],
            'loan_number' => [
                'sometimes', 'string', 'max:50',
                Rule::unique('loans', 'loan_number')
                    ->where('book_id', $bookId)
                    ->ignore($loanId),
            ],
            'loan_amount' => ['sometimes', 'numeric', 'min:1'],
            'interest_amount' => ['sometimes', 'numeric', 'min:0'],
            'loan_type' => ['sometimes', 'in:daily,weekly,monthly'],
            'line' => ['sometimes', 'in:line1,line2,line3,line4,line5,line6'],
            'issued_date' => ['sometimes', 'date'],
        ];
    }
}
