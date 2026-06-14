<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates one user per role so the API can be tested end-to-end.
     * Login uses username + password; password is auto-hashed via the
     * User model's 'hashed' cast. Keyed on username so re-seeding is idempotent.
     */
    public function run(): void
    {
        // Default book the seeded book_admin/agent belong to. Real books created
        // via the API get random UUIDs (HasUuids); only this dev-seed book uses a
        // fixed sentinel UUID so the frontend's DEFAULT_BOOK_ID fallback (the old
        // "?? 1" default) resolves to a book that actually exists.
        $book = Book::updateOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['name' => 'Balaji Finance', 'owner_name' => 'Book Owner', 'is_active' => true, 'is_deleted' => false]
        );

        $settings = [
            'APP_NAME' => 'TapNTrack',
            'DAYS_TO_PAY' => '120',
            'INTEREST_PERCENTAGE' => '20',
        ];
        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(
                ['book_id' => $book->id, 'key' => $key],
                ['value' => $value]
            );
        }

        // A few sample customers for book 1 so the list isn't empty
        $customers = [
            ['name' => 'Ramesh Kumar', 'father_name' => 'Suresh Kumar', 'phone' => '9000000001', 'address' => '12 Anna Nagar, Chennai', 'profession' => 'Shop Owner'],
            ['name' => 'Priya Devi', 'father_name' => 'Murugan', 'phone' => '9000000002', 'address' => '45 Gandhi St, Madurai', 'profession' => 'Tailor'],
            ['name' => 'Karthik Raja', 'father_name' => 'Velu', 'phone' => '9000000003', 'address' => '7 Bazaar Rd, Trichy', 'profession' => 'Farmer'],
        ];
        foreach ($customers as $c) {
            Customer::updateOrCreate(
                ['book_id' => $book->id, 'phone' => $c['phone']],
                array_merge($c, ['book_id' => $book->id, 'is_active' => true])
            );
        }

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
                'book_id' => $book->id,
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
                'book_id' => $book->id,
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
