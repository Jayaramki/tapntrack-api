<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates one user per role so the API can be tested end-to-end.
     * Login uses username + password; password is auto-hashed via the
     * User model's 'hashed' cast. Keyed on username so re-seeding is idempotent.
     */
    public function run(): void
    {
        $password = 'Admin@123';

        $users = [
            [
                'username' => 'superadmin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'superadmin@tapntrack.in',
                'role' => 'super_admin',
                'book_id' => null,
                'phone' => '9876543210',
                'security_question' => 'What is your pet name?',
                'security_answer' => 'tommy',
            ],
            [
                'username' => 'bookadmin',
                'first_name' => 'Book',
                'last_name' => 'Admin',
                'email' => 'bookadmin@tapntrack.in',
                'role' => 'book_admin',
                'book_id' => 1,
                'phone' => '9876543211',
                'security_question' => 'What is your mother maiden name?',
                'security_answer' => 'lakshmi',
            ],
            [
                'username' => 'agent',
                'first_name' => 'Field',
                'last_name' => 'Agent',
                'email' => 'agent@tapntrack.in',
                'role' => 'field_agent',
                'book_id' => 1,
                'phone' => '9876543212',
                'security_question' => 'What is your school name?',
                'security_answer' => 'vivekananda',
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['username' => $data['username']],
                array_merge($data, [
                    'name' => $data['first_name'].' '.$data['last_name'],
                    'password' => $password,
                    'is_active' => true,
                    'permissions' => null,
                    'api_token' => null,
                ])
            );
        }
    }
}
