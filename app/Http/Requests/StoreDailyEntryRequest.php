<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'loan_id' => ['required', 'uuid', 'exists:loans,id'],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'mode' => ['required', 'in:cash,gpay'],
        ];
    }
}
