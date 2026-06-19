<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\UpdateLoanRequest;
use App\Models\AppSetting;
use App\Models\ArchivedLoan;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoanController extends ApiController
{
    /** Overdue thresholds (days since last activity) by loan type. */
    private const OVERDUE_DAYS = ['daily' => 3, 'weekly' => 14, 'monthly' => 60];

    /** Active (not soft-deleted) loans for a book. */
    public function index(Request $request): JsonResponse
    {
        $bookId = $this->validatedBookId($request);

        if ($deny = $this->denyBookAccess($bookId)) {
            return $deny;
        }

        $loans = Loan::with('customer:id,name')
            ->where('book_id', $bookId)
            ->where('is_deleted', false)
            ->orderByDesc('issued_date')
            ->get()
            ->map(fn (Loan $l) => $this->present($l));

        return $this->success($loans);
    }

    /** Soft-deleted loans for a book. */
    public function deleted(Request $request): JsonResponse
    {
        $bookId = $this->validatedBookId($request);

        if ($deny = $this->denyBookAccess($bookId)) {
            return $deny;
        }

        $loans = Loan::with('customer:id,name')
            ->where('book_id', $bookId)
            ->where('is_deleted', true)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Loan $l) => $this->present($l));

        return $this->success($loans);
    }

    /** Archived (completed) loans for a book. */
    public function archived(Request $request): JsonResponse
    {
        $bookId = $this->validatedBookId($request);

        if ($deny = $this->denyBookAccess($bookId)) {
            return $deny;
        }

        $loans = ArchivedLoan::with('customer:id,name')
            ->where('book_id', $bookId)
            ->orderByDesc('archived_at')
            ->get()
            ->map(fn (ArchivedLoan $l) => $this->presentArchived($l));

        return $this->success($loans);
    }

    /** Active, not-yet-completed loans with pending-days / overdue flags. */
    public function pending(Request $request): JsonResponse
    {
        $bookId = $this->validatedBookId($request);

        if ($deny = $this->denyBookAccess($bookId)) {
            return $deny;
        }

        $loans = Loan::with('customer:id,name')
            ->where('book_id', $bookId)
            ->where('is_deleted', false)
            ->whereNull('completed_date')
            ->orderByDesc('issued_date')
            ->get()
            ->map(function (Loan $l) {
                $days = $this->pendingDays($l);

                return $this->present($l) + [
                    'act_pending_days' => $days,
                    'is_overdue' => $days > (self::OVERDUE_DAYS[$l->loan_type] ?? PHP_INT_MAX),
                ];
            })
            ->filter(fn (array $l) => $l['act_pending_days'] > 0)
            ->values();

        return $this->success($loans);
    }

    public function show(string $id): JsonResponse
    {
        $loan = Loan::with('customer:id,name')->find($id);

        if (! $loan) {
            return $this->error('Loan not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $loan->book_id)) {
            return $deny;
        }

        return $this->success($this->present($loan));
    }

    /**
     * Suggest the next auto loan number for a book, collision-safe (scans active
     * + soft-deleted + archived loans so no number is ever reused). Format from
     * the book's settings: {PREFIX}{YY}-{NNN} with yearly reset, or {PREFIX}{NNN}
     * with no reset. Returns the mode too so the SPA only prefills when 'auto'.
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'date' => ['nullable', 'date'],
        ]);
        if ($deny = $this->denyBookAccess($data['book_id'])) {
            return $deny;
        }

        $bookId = $data['book_id'];
        $settings = AppSetting::where('book_id', $bookId)->pluck('value', 'key');
        $mode = $settings['LOAN_NUMBER_MODE'] ?? 'manual';
        $reset = $settings['LOAN_NUMBER_RESET'] ?? 'yearly';
        $prefix = (string) ($settings['LOAN_NUMBER_PREFIX'] ?? '');

        $year = isset($data['date']) ? Carbon::parse($data['date'])->year : now()->year;
        $stub = $reset === 'yearly' ? $prefix.substr((string) $year, -2).'-' : $prefix;

        $existing = Loan::where('book_id', $bookId)->pluck('loan_number')
            ->merge(ArchivedLoan::where('book_id', $bookId)->pluck('loan_number'));

        $max = 0;
        foreach ($existing as $num) {
            $num = (string) $num;
            if ($stub !== '' && ! str_starts_with($num, $stub)) {
                continue;
            }
            $suffix = $stub === '' ? $num : substr($num, strlen($stub));
            if (preg_match('/^(\d+)/', $suffix, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $stub.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);

        return $this->success(['mode' => $mode, 'reset' => $reset, 'prefix' => $prefix, 'next_number' => $next]);
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        if ($deny = $this->denyBookAccess((string) $request->input('book_id'))) {
            return $deny;
        }
        if ($deny = $this->denyPlanLimit('loan')) {
            return $deny;
        }

        $loan = Loan::create([
            'book_id' => $request->input('book_id'),
            'customer_id' => $request->input('customer_id'),
            'loan_number' => $request->input('loan_number'),
            'loan_amount' => $request->input('loan_amount'),
            'interest_amount' => $request->input('interest_amount'),
            'loan_type' => $request->input('loan_type'),
            'line' => $request->input('line'),
            'issued_date' => $request->input('issued_date'),
        ]);

        $loan->load('customer:id,name');

        return $this->success($this->present($loan), 'Loan created successfully', 201);
    }

    public function update(UpdateLoanRequest $request, string $id): JsonResponse
    {
        $loan = Loan::find($id);

        if (! $loan) {
            return $this->error('Loan not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $loan->book_id)) {
            return $deny;
        }

        $loan->update($request->only([
            'customer_id', 'loan_number', 'loan_amount', 'interest_amount',
            'loan_type', 'line', 'issued_date',
        ]));

        $loan->load('customer:id,name');

        return $this->success($this->present($loan), 'Loan updated successfully');
    }

    /** Soft-delete (recoverable). */
    public function destroy(string $id): JsonResponse
    {
        $loan = Loan::find($id);

        if (! $loan) {
            return $this->error('Loan not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $loan->book_id)) {
            return $deny;
        }

        $loan->update(['is_deleted' => true]);

        return $this->success(null, 'Loan deleted');
    }

    /** Restore a soft-deleted loan. */
    public function restore(string $id): JsonResponse
    {
        $loan = Loan::find($id);

        if (! $loan) {
            return $this->error('Loan not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $loan->book_id)) {
            return $deny;
        }

        $loan->update(['is_deleted' => false]);
        $loan->load('customer:id,name');

        return $this->success($this->present($loan), 'Loan restored');
    }

    /** Move an active loan into the archive. */
    public function archive(string $id): JsonResponse
    {
        $loan = Loan::find($id);

        if (! $loan) {
            return $this->error('Loan not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $loan->book_id)) {
            return $deny;
        }

        DB::transaction(function () use ($loan) {
            ArchivedLoan::create([
                'book_id' => $loan->book_id,
                'customer_id' => $loan->customer_id,
                'loan_number' => $loan->loan_number,
                'loan_amount' => $loan->loan_amount,
                'interest_amount' => $loan->interest_amount,
                'loan_type' => $loan->loan_type,
                'line' => $loan->line,
                'issued_date' => $loan->issued_date,
                'completed_date' => $loan->completed_date ?? now()->toDateString(),
                'total_collected' => $loan->total_collected,
                'archived_at' => now(),
            ]);

            $loan->delete();
        });

        return $this->success(null, 'Loan archived');
    }

    /** Move an archived loan back to active loans. */
    public function unarchive(string $id): JsonResponse
    {
        $archived = ArchivedLoan::find($id);

        if (! $archived) {
            return $this->error('Archived loan not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $archived->book_id)) {
            return $deny;
        }

        $loan = DB::transaction(function () use ($archived) {
            $loan = Loan::create([
                'book_id' => $archived->book_id,
                'customer_id' => $archived->customer_id,
                'loan_number' => $archived->loan_number,
                'loan_amount' => $archived->loan_amount,
                'interest_amount' => $archived->interest_amount,
                'loan_type' => $archived->loan_type,
                'line' => $archived->line,
                'issued_date' => $archived->issued_date,
                'completed_date' => null,
                'total_collected' => $archived->total_collected,
            ]);

            $archived->delete();

            return $loan;
        });

        $loan->load('customer:id,name');

        return $this->success($this->present($loan), 'Loan restored from archive');
    }

    /** Permanently delete an archived loan. */
    public function permanentDelete(string $id): JsonResponse
    {
        $archived = ArchivedLoan::find($id);

        if (! $archived) {
            return $this->error('Archived loan not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $archived->book_id)) {
            return $deny;
        }

        $archived->delete();

        return $this->success(null, 'Loan permanently deleted');
    }

    /** Permanently delete a soft-deleted loan. */
    public function hardDelete(string $id): JsonResponse
    {
        $loan = Loan::find($id);

        if (! $loan) {
            return $this->error('Loan not found', [], 404);
        }

        if ($deny = $this->denyBookAccess((string) $loan->book_id)) {
            return $deny;
        }

        $loan->delete();

        return $this->success(null, 'Loan permanently deleted');
    }

    private function validatedBookId(Request $request): string
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
        ]);

        return (string) $data['book_id'];
    }

    /** Days since the loan's last activity (issued_date until daily-entries land). */
    private function pendingDays(Loan $loan): int
    {
        $last = Carbon::parse($loan->issued_date)->startOfDay();

        return max(0, $last->diffInDays(Carbon::now()->startOfDay(), false));
    }

    /** Shape a loan for the API: numeric money fields + computed columns. */
    private function present(Loan $loan): array
    {
        $amount = (float) $loan->loan_amount;
        $interest = (float) $loan->interest_amount;
        $collected = (float) $loan->total_collected;

        return [
            'id' => $loan->id,
            'book_id' => (string) $loan->book_id,
            'customer_id' => (string) $loan->customer_id,
            'loan_number' => $loan->loan_number,
            'loan_amount' => $amount,
            'interest_amount' => $interest,
            'loan_type' => $loan->loan_type,
            'line' => $loan->line,
            'issued_date' => $loan->issued_date,
            'completed_date' => $loan->completed_date,
            'is_deleted' => (bool) $loan->is_deleted,
            'created_at' => $loan->created_at,
            'updated_at' => $loan->updated_at,
            'customer_name' => $loan->customer?->name ?? '',
            'total_collected' => $collected,
            // Interest is withheld upfront; the customer repays loan_amount in full.
            'remaining_balance' => round($amount - $collected, 2),
        ];
    }

    /** Shape an archived loan (no is_deleted; adds archived_at). */
    private function presentArchived(ArchivedLoan $loan): array
    {
        $amount = (float) $loan->loan_amount;
        $interest = (float) $loan->interest_amount;
        $collected = (float) $loan->total_collected;

        return [
            'id' => $loan->id,
            'book_id' => (string) $loan->book_id,
            'customer_id' => (string) $loan->customer_id,
            'loan_number' => $loan->loan_number,
            'loan_amount' => $amount,
            'interest_amount' => $interest,
            'loan_type' => $loan->loan_type,
            'line' => $loan->line,
            'issued_date' => $loan->issued_date,
            'completed_date' => $loan->completed_date,
            'archived_at' => $loan->archived_at,
            'created_at' => $loan->created_at,
            'updated_at' => $loan->updated_at,
            'customer_name' => $loan->customer?->name ?? '',
            'total_collected' => $collected,
            // Interest is withheld upfront; the customer repays loan_amount in full.
            'remaining_balance' => round($amount - $collected, 2),
        ];
    }
}
