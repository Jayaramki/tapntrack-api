<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyEntry;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerController extends ApiController
{
    /** GET /ledger?book_id=&year=&month= — month collection grid (one row per active loan). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $year = (int) $data['year'];
        $month = (int) $data['month'];
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;
        $days = range(1, $daysInMonth);

        // Query 1: active loans for the book (+ customer name).
        $loans = Loan::with('customer:id,name')
            ->where('book_id', $data['book_id'])
            ->where('is_deleted', false)
            ->orderBy('loan_number')
            ->get();

        // Query 2: this month's entries for the book, indexed by loan + date.
        $entriesByLoanDate = DailyEntry::where('book_id', $data['book_id'])
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('loan_id')
            ->map(fn ($group) => $group->keyBy(fn (DailyEntry $e) => Carbon::parse($e->entry_date)->toDateString()));

        $rows = $loans->map(function (Loan $loan) use ($days, $year, $month, $entriesByLoanDate) {
            $loanEntries = $entriesByLoanDate->get($loan->id);

            $cells = [];
            foreach ($days as $d) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $entry = $loanEntries?->get($dateStr);
                $cells[$dateStr] = [
                    'id' => $entry?->id,
                    'loan_id' => (string) $loan->id,
                    'date' => $dateStr,
                    'amount' => $entry ? (float) $entry->amount : null,
                    'mode' => $entry?->mode,
                ];
            }

            $amount = (float) $loan->loan_amount;
            $interest = (float) $loan->interest_amount;
            $collected = (float) $loan->total_collected;

            return [
                'loan_id' => (string) $loan->id,
                'loan_number' => $loan->loan_number,
                'customer_name' => $loan->customer?->name ?? '',
                'loan_amount' => $amount,
                'line' => $loan->line,
                'total_collected' => $collected,
                'remaining_balance' => round($amount + $interest - $collected, 2),
                'cells' => $cells,
            ];
        });

        return $this->success([
            'book_id' => (string) $data['book_id'],
            'year' => $year,
            'month' => $month,
            'days' => $days,
            'rows' => $rows,
        ]);
    }
}
