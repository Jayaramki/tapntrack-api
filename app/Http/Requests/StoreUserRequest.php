<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:super_admin,book_admin,field_agent'],
            // Required for any non-super_admin role; super_admin is global (null book).
            'book_id' => ['nullable', 'uuid', 'exists:books,id', 'required_unless:role,super_admin'],
            'phone' => ['nullable', 'string', 'max:20'],
            'security_question' => ['nullable', 'string', 'max:255'],
            'security_answer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
