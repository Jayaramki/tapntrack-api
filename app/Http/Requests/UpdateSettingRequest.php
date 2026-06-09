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
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'key' => ['required', 'string', 'in:APP_NAME,DAYS_TO_PAY,INTEREST_PERCENTAGE'],
            'value' => ['required', 'string', 'max:255'],
        ];
    }
}
