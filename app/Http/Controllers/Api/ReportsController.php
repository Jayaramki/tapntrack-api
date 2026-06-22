<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyEntry;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends ApiController
{
    /** GET /reports/collections — daily collections in a date range, optional type/line filter. */
    public function collections(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
            'loan_type' => ['nullable', 'in:daily,weekly,monthly'],
            'line' => ['nullable', 'string', 'max:50'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $entries = DailyEntry::with(['loan:id,loan_number,line,loan_type,customer_id', 'loan.customer:id,name,customer_number'])
            ->where('book_id', $data['book_id'])
            ->whereBetween('entry_date', [$data['from_date'], $data['to_date']])
            ->when(! empty($data['loan_type']), fn ($q) => $q->whereHas('loan', fn ($w) => $w->where('loan_type', $data['loan_type'])))
            ->when(! empty($data['line']), fn ($q) => $q->whereHas('loan', fn ($w) => $w->where('line', $data['line'])))
            ->orderBy('entry_date')
            ->get()
            ->map(fn (DailyEntry $e) => [
                'date' => $e->entry_date,
                'loan_number' => $e->loan?->loan_number ?? '',
                'customer_name' => $e->loan?->customer?->name ?? '',
                'customer_number' => $e->loan?->customer?->customer_number,
                'amount' => (float) $e->amount,
                'mode' => $e->mode,
            ]);

        return $this->success($entries);
    }

    /** GET /reports/loans — loan portfolio with collected/balance, optional type/line filter. */
    public function loans(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'loan_type' => ['nullable', 'in:daily,weekly,monthly'],
            'line' => ['nullable', 'string', 'max:50'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $loans = Loan::with('customer:id,name,customer_number')
            ->where('book_id', $data['book_id'])
            ->where('is_deleted', false)
            ->when(! empty($data['loan_type']), fn ($q) => $q->where('loan_type', $data['loan_type']))
            ->when(! empty($data['line']), fn ($q) => $q->where('line', $data['line']))
            ->orderBy('loan_number')
            ->get()
            ->map(function (Loan $l) {
                $amount = (float) $l->loan_amount;
                $collected = (float) $l->total_collected;

                return [
                    'loan_number' => $l->loan_number,
                    'customer_name' => $l->customer?->name ?? '',
                    'customer_number' => $l->customer?->customer_number,
                    'loan_amount' => $amount,
                    'total_collected' => $collected,
                    'remaining_balance' => round($amount - $collected, 2),
                    'issued_date' => $l->issued_date,
                    'completed_date' => $l->completed_date,
                    'loan_type' => $l->loan_type,
                    'line' => $l->line,
                ];
            });

        return $this->success($loans);
    }
}
