<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('users', 'username')
                    ->ignore($this->route('id'))
                    ->where(fn ($q) => $q->where('tenant_id', $this->user()?->tenant_id)),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'role' => ['sometimes', 'required', 'in:super_admin,tenant_admin,book_admin,field_agent'],
            'book_id' => ['sometimes', 'nullable', 'uuid', 'exists:books,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'security_question' => ['nullable', 'string', 'max:255'],
            'security_answer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
