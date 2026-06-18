<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Username is unique per tenant, not globally.
            'username' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'username')->where(
                    fn ($q) => $q->where('tenant_id', $this->user()?->tenant_id)
                ),
            ],
            'password' => ['required', 'string', 'min:6'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:super_admin,tenant_admin,book_admin,field_agent'],
            // Required for book-pinned roles; super_admin/tenant_admin span (null book).
            'book_id' => ['nullable', 'uuid', 'exists:books,id', 'required_unless:role,super_admin,tenant_admin'],
            'phone' => ['nullable', 'string', 'max:20'],
            'security_question' => ['nullable', 'string', 'max:255'],
            'security_answer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
