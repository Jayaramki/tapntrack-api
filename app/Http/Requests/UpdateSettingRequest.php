<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'key' => ['required', 'string', 'in:APP_NAME,DAYS_TO_PAY,INTEREST_PERCENTAGE,LOAN_NUMBER_MODE,LOAN_NUMBER_RESET,LOAN_NUMBER_PREFIX'],
            // nullable so an empty prefix is allowed.
            'value' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }
}
