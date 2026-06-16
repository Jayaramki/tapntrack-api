<?php

namespace App\Http\Controllers\Api;

use App\Models\ArchivedLoan;
use App\Models\Customer;
use App\Models\DailyEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends ApiController
{
    private const OVERDUE_DAYS = ['daily' => 3, 'weekly' => 14, 'monthly' => 60];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $bookId = $data['book_id'];
        $from = $data['from'];
        $to = $data['to'];

        // Collections within the range
        $entries = DailyEntry::where('book_id', $bookId)
            ->whereBetween('entry_date', [$from, $to])->get();
        $cash = (float) $entries->where('mode', 'cash')->sum('amount');
        $gpay = (float) $entries->where('mode', 'gpay')->sum('amount');
        $collection = $cash + $gpay;

        // Expenses within the range, broken down by active category
        $expenses = Expense::where('book_id', $bookId)
            ->where('is_active', true)
            ->whereBetween('expense_date', [$from, $to])->get();
        $expenseOverall = (float) $expenses->sum('amount');
        $expenseByCategory = [];
        foreach (ExpenseCategory::where('book_id', $bookId)->where('is_active', true)->orderBy('name')->get() as $cat) {
            $expenseByCategory[$cat->name] = (float) $expenses->where('category', $cat->name)->sum('amount');
        }

        // Interest is the lender's profit (withheld upfront) across all loans
        $interestIncome = (float) Loan::where('book_id', $bookId)->sum('interest_amount');

        $activeLoans = Loan::where('book_id', $bookId)
            ->where('is_deleted', false)
            ->whereNull('completed_date')->get();

        $completedThisPeriod = Loan::where('book_id', $bookId)
            ->whereBetween('completed_date', [$from, $to])->count()
            + ArchivedLoan::where('book_id', $bookId)
                ->whereBetween('completed_date', [$from, $to])->count();

        // Pending = active loans overdue (days since last collection > threshold)
        $lastEntry = DailyEntry::where('book_id', $bookId)
            ->selectRaw('loan_id, MAX(entry_date) as last_date')
            ->groupBy('loan_id')->pluck('last_date', 'loan_id');
        $today = Carbon::now()->startOfDay();
        $pending = $activeLoans->filter(function (Loan $l) use ($lastEntry, $today) {
            $last = Carbon::parse($lastEntry[$l->id] ?? $l->issued_date)->startOfDay();
            $days = $last->diffInDays($today, false);

            return $days > (self::OVERDUE_DAYS[$l->loan_type] ?? PHP_INT_MAX);
        })->count();

        return $this->success([
            'book_id' => (string) $bookId,
            'date_range' => ['from' => $from, 'to' => $to],
            'total_cash' => $cash,
            'total_gpay' => $gpay,
            'total_collection' => $collection,
            'total_interest_income' => $interestIncome,
            'income_overall' => $collection,
            'expense_by_category' => (object) $expenseByCategory,
            'expense_overall' => $expenseOverall,
            'net_profit' => round($collection - $expenseOverall, 2),
            'active_loans' => $activeLoans->count(),
            'completed_loans_this_period' => $completedThisPeriod,
            'pending_loans' => $pending,
            'total_customers' => Customer::where('book_id', $bookId)->count(),
        ]);
    }
}
