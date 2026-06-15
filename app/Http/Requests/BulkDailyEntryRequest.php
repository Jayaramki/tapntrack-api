<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDailyEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'entry_date' => ['required', 'date'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.loan_id' => ['required', 'uuid', 'exists:loans,id'],
            'entries.*.amount' => ['required', 'numeric', 'gt:0'],
            'entries.*.mode' => ['required', 'in:cash,gpay'],
        ];
    }
}
