<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * Slugs that would collide with real URL path segments or platform routes.
     * The slug is the first URL segment (app.tapntrack.in/<slug>/...), so these
     * must never be claimed by a tenant.
     */
    public const RESERVED_SLUGS = [
        'login', 'logout', 'register', 'signup', 'auth', 'api', 'admin', 'app',
        'www', 'assets', 'static', 'public', 'dashboard', 'books', 'users',
        'customers', 'loans', 'expenses', 'reports', 'masters', 'settings',
        'profile', 'ledger', 'billing', 'subscription', 'tenant', 'tenants',
        'health', 'webhooks', 'support', 'help', 'about', 'pricing', 'home',
        // app-route first segments (must match the SPA's RESERVED_SEGMENTS)
        'forgot-password', 'change-password', 'pending-loans', 'daily-entry',
        'day-summary',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]);
        }
    }

    public function rules(): array
    {
        return [
            // Tenant
            'tenant_name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'min:3', 'max:32',
                // lowercase letters/digits, single hyphens between, no leading/trailing hyphen
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(self::RESERVED_SLUGS),
                'unique:tenants,slug',
            ],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],

            // First tenant_admin user
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_.]+$/'],
            'password' => ['required', 'string', 'min:6'],
            'security_question' => ['nullable', 'string', 'max:255'],
            'security_answer' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The workspace handle may only contain lowercase letters, numbers and hyphens.',
            'slug.not_in' => 'That workspace handle is reserved. Please choose another.',
            'slug.unique' => 'That workspace handle is already taken.',
            'username.regex' => 'The username may only contain letters, numbers, dots and underscores.',
        ];
    }
}
