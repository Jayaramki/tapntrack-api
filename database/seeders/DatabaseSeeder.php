<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\ArchivedLoan;
use App\Models\Book;
use App\Models\Customer;
use App\Models\DailyEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Line;
use App\Models\Loan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds a complete, realistic dataset for one book so every screen has data.
     * Idempotent (updateOrCreate on natural keys), so it is safe to re-run.
     * Lending model: interest is withheld upfront — the customer repays
     * loan_amount in full; total_collected is kept in sync with daily entries.
     */
    public function run(): void
    {
        // Default tenant — pre-SaaS data is folded under this one account. Fixed
        // sentinel so a fresh seed and the migration backfill converge.
        $tenant = Tenant::updateOrCreate(
            ['id' => Tenant::DEFAULT_TENANT_ID],
            ['slug' => 'balaji', 'name' => 'Balaji Finance', 'owner_name' => 'Balaji',
             'email' => 'owner@tapntrack.in', 'status' => 'active', 'plan' => 'premium', 'is_deleted' => false]
        );

        // Fixed sentinel UUID so the frontend DEFAULT_BOOK_ID fallback resolves.
        $book = Book::updateOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['tenant_id' => $tenant->id, 'name' => 'Balaji Finance', 'owner_name' => 'Balaji', 'is_active' => true, 'is_deleted' => false]
        );

        foreach ([
            'APP_NAME' => 'Balaji Finance', 'DAYS_TO_PAY' => '100', 'INTEREST_PERCENTAGE' => '10',
            'LOAN_NUMBER_MODE' => 'manual', 'LOAN_NUMBER_RESET' => 'yearly', 'LOAN_NUMBER_PREFIX' => 'BF-',
        ] as $key => $value) {
            AppSetting::updateOrCreate(['book_id' => $book->id, 'key' => $key], ['value' => $value]);
        }

        foreach ([
            ['Cheetu', '#E65100'], ['Vatti', '#C62828'], ['GPay', '#1565C0'], ['Other', '#546E7A'],
        ] as [$name, $color]) {
            ExpenseCategory::updateOrCreate(['book_id' => $book->id, 'name' => $name], ['color' => $color, 'is_active' => true]);
        }

        foreach (['Line 1', 'Line 2', 'Line 3', 'Line 4', 'Line 5', 'Line 6'] as $lineName) {
            Line::updateOrCreate(['book_id' => $book->id, 'name' => $lineName], ['color' => '#546E7A', 'is_active' => true]);
        }

        $this->seedUsers($tenant->id, $book->id);

        // ── Customers ──────────────────────────────────────────────────────────
        $customerDefs = [
            ['Ramesh Kumar', 'Suresh Kumar', '9000000001', '12 Anna Nagar, Chennai', 'Shop Owner'],
            ['Priya Devi', 'Murugan', '9000000002', '45 Gandhi St, Madurai', 'Tailor'],
            ['Karthik Raja', 'Velu', '9000000003', '7 Bazaar Rd, Trichy', 'Farmer'],
            ['Lakshmi Narayan', 'Narayan Iyer', '9000000004', '88 West Mada St, Chennai', 'Vegetable Vendor'],
            ['Suresh Babu', 'Babu Rao', '9000000005', '23 Kamaraj Rd, Salem', 'Mechanic'],
            ['Meena Kumari', 'Sundaram', '9000000006', '5 Temple St, Thanjavur', 'Flower Seller'],
            ['Anand Raj', 'Rajappa', '9000000007', '90 Mount Rd, Chennai', 'Auto Driver'],
            ['Devi Shankar', 'Shankar', '9000000008', '14 Market St, Erode', 'Provision Store'],
        ];
        $customers = [];
        foreach ($customerDefs as [$name, $father, $phone, $address, $profession]) {
            $customers[$phone] = Customer::updateOrCreate(
                ['book_id' => $book->id, 'phone' => $phone],
                ['name' => $name, 'father_name' => $father, 'address' => $address, 'profession' => $profession, 'is_active' => true]
            );
        }

        // ── Loans (active) ─────────────────────────────────────────────────────
        // [loan_number, customer phone, line, type, amount, interest, issued]
        $loanDefs = [
            ['BF-001', '9000000001', 'Line 1', 'daily', 10000, 1000, '2026-04-01'],
            ['BF-002', '9000000002', 'Line 1', 'daily', 15000, 1500, '2026-04-10'],
            ['BF-003', '9000000003', 'Line 2', 'weekly', 20000, 2000, '2026-03-15'],
            ['BF-004', '9000000004', 'Line 2', 'daily', 8000, 800, '2026-05-01'],
            ['BF-005', '9000000005', 'Line 3', 'daily', 25000, 2500, '2026-05-10'],
            ['BF-006', '9000000006', 'Line 3', 'monthly', 30000, 3000, '2026-02-20'],
            ['BF-007', '9000000007', 'Line 4', 'daily', 12000, 1200, '2026-05-20'],
            ['BF-008', '9000000008', 'Line 5', 'weekly', 18000, 1800, '2026-06-01'],
        ];
        $loans = [];
        foreach ($loanDefs as [$num, $phone, $line, $type, $amount, $interest, $issued]) {
            $loans[$num] = Loan::updateOrCreate(
                ['book_id' => $book->id, 'loan_number' => $num],
                [
                    'customer_id' => $customers[$phone]->id,
                    'loan_amount' => $amount, 'interest_amount' => $interest,
                    'loan_type' => $type, 'line' => $line, 'issued_date' => $issued,
                    'completed_date' => null, 'is_deleted' => false,
                ]
            );
        }

        // ── Daily entries (drives total_collected; mix of current + overdue) ─────
        // loan_number => [[date, amount, mode], ...]
        $entryDefs = [
            'BF-001' => [['2026-06-11', 200, 'cash'], ['2026-06-12', 200, 'cash'], ['2026-06-13', 200, 'gpay'], ['2026-06-14', 200, 'cash'], ['2026-06-15', 200, 'cash']],
            'BF-002' => [['2026-06-10', 300, 'cash'], ['2026-06-12', 300, 'gpay'], ['2026-06-14', 300, 'cash']],
            'BF-003' => [['2026-05-25', 1000, 'cash'], ['2026-06-01', 1000, 'gpay'], ['2026-06-08', 1000, 'cash']],
            'BF-004' => [['2026-05-15', 150, 'cash'], ['2026-05-18', 150, 'cash'], ['2026-05-20', 150, 'gpay']], // overdue (stale)
            'BF-005' => [['2026-06-12', 500, 'cash'], ['2026-06-13', 500, 'gpay'], ['2026-06-14', 500, 'cash'], ['2026-06-15', 500, 'cash']],
            'BF-006' => [['2026-04-20', 5000, 'cash'], ['2026-05-20', 5000, 'gpay']],
            'BF-007' => [['2026-05-22', 200, 'cash'], ['2026-05-24', 200, 'cash']], // overdue (stale)
            'BF-008' => [['2026-06-08', 1500, 'gpay'], ['2026-06-15', 1500, 'cash']],
        ];
        foreach ($entryDefs as $num => $entries) {
            $loan = $loans[$num];
            foreach ($entries as [$date, $amount, $mode]) {
                DailyEntry::updateOrCreate(
                    ['loan_id' => $loan->id, 'entry_date' => $date],
                    ['book_id' => $book->id, 'amount' => $amount, 'mode' => $mode]
                );
            }
            $loan->update(['total_collected' => DailyEntry::where('loan_id', $loan->id)->sum('amount')]);
        }

        // ── A soft-deleted loan (Deleted tab) and an archived loan (Archived tab)
        Loan::updateOrCreate(
            ['book_id' => $book->id, 'loan_number' => 'BF-D01'],
            [
                'customer_id' => $customers['9000000002']->id,
                'loan_amount' => 5000, 'interest_amount' => 500, 'loan_type' => 'daily',
                'line' => 'Line 1', 'issued_date' => '2026-01-10', 'completed_date' => null,
                'total_collected' => 1200, 'is_deleted' => true,
            ]
        );
        ArchivedLoan::updateOrCreate(
            ['book_id' => $book->id, 'loan_number' => 'BF-A01'],
            [
                'customer_id' => $customers['9000000003']->id,
                'loan_amount' => 10000, 'interest_amount' => 1000, 'loan_type' => 'daily',
                'line' => 'Line 2', 'issued_date' => '2025-12-01', 'completed_date' => '2026-03-15',
                'total_collected' => 10000, 'archived_at' => '2026-03-16 10:00:00',
            ]
        );

        // ── Expenses (keyed on book + date + description) ───────────────────────
        $expenseDefs = [
            ['2026-06-01', 'Office Rent - June', 'Other', 5000],
            ['2026-06-03', 'GPay transaction charges', 'GPay', 200],
            ['2026-06-05', 'Cheetu monthly payment', 'Cheetu', 8000],
            ['2026-06-10', 'Vatti - capital interest', 'Vatti', 2500],
            ['2026-06-12', 'Tea & snacks for staff', 'Other', 300],
            ['2026-06-14', 'Fuel for collection rounds', 'Other', 800],
        ];
        foreach ($expenseDefs as [$date, $desc, $category, $amount]) {
            Expense::updateOrCreate(
                ['book_id' => $book->id, 'expense_date' => $date, 'description' => $desc],
                ['category' => $category, 'amount' => $amount, 'is_active' => true]
            );
        }
    }

    private function seedUsers(string $tenantId, string $bookId): void
    {
        // superadmin = platform owner (spans all tenants). tenantadmin owns this
        // tenant's books/users/billing. bookadmin/agents are pinned to the book.
        $users = [
            ['superadmin', 'Super', 'Admin', 'super_admin', null, '9876543210', 'What is your pet name?', 'tommy'],
            ['tenantadmin', 'Tenant', 'Admin', 'tenant_admin', null, '9876543219', 'What is your birth city?', 'chennai'],
            ['bookadmin', 'Book', 'Admin', 'book_admin', $bookId, '9876543211', 'What is your mother maiden name?', 'lakshmi'],
            ['agent', 'Field', 'Agent', 'field_agent', $bookId, '9876543212', 'What is your school name?', 'vivekananda'],
            ['agent2', 'Second', 'Agent', 'field_agent', $bookId, '9876543213', 'What is your favourite colour?', 'blue'],
        ];

        foreach ($users as [$username, $first, $last, $role, $bid, $phone, $q, $a]) {
            User::updateOrCreate(
                ['tenant_id' => $tenantId, 'username' => $username],
                [
                    'name' => "$first $last",
                    'email' => "$username@tapntrack.in",
                    'first_name' => $first, 'last_name' => $last,
                    'role' => $role, 'book_id' => $bid, 'phone' => $phone,
                    'security_question' => $q, 'security_answer' => $a,
                    'password' => 'Admin@123', 'is_active' => true, 'is_deleted' => false,
                    'permissions' => null, 'api_token' => null,
                ]
            );
        }
    }
}
