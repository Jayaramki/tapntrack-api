<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\BulkDailyEntryRequest;
use App\Http\Requests\StoreDailyEntryRequest;
use App\Http\Requests\UpdateDailyEntryRequest;
use App\Models\DailyEntry;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyEntryController extends ApiController
{
    private const WITH = ['loan:id,loan_number,line,customer_id', 'loan.customer:id,name'];

    /** GET /daily-entries?book_id=&date= — collections for a book on a date. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'date' => ['nullable', 'date'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $entries = DailyEntry::with(self::WITH)
            ->where('book_id', $data['book_id'])
            ->when($request->filled('date'), fn ($q) => $q->where('entry_date', $data['date']))
            ->orderByDesc('entry_date')
            ->get()
            ->map(fn (DailyEntry $e) => $this->present($e));

        return $this->success($entries);
    }

    /** GET /daily-entries/by-loan?loan_id= — payment history for a loan. */
    public function byLoan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_id' => ['required', 'uuid', 'exists:loans,id'],
        ]);

        $loan = Loan::find($data['loan_id']);
        if ($deny = $this->denyBookAccess((string) $loan->book_id)) {
            return $deny;
        }

        $entries = DailyEntry::with(self::WITH)
            ->where('loan_id', $data['loan_id'])
            ->orderBy('entry_date')
            ->get()
            ->map(fn (DailyEntry $e) => $this->present($e));

        return $this->success($entries);
    }

    /** GET /daily-entries/summary?book_id=&date= — cash/gpay/collection/net for a day. */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
            'date' => ['required', 'date'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $entries = DailyEntry::with(self::WITH)
            ->where('book_id', $data['book_id'])
            ->where('entry_date', $data['date'])
            ->orderByDesc('amount')
            ->get();

        $cash = (float) $entries->where('mode', 'cash')->sum('amount');
        $gpay = (float) $entries->where('mode', 'gpay')->sum('amount');
        $collection = $cash + $gpay;
        // Expenses domain not built yet — total_expenses stays 0 until then.
        $expenses = 0.0;

        return $this->success([
            'date' => $data['date'],
            'book_id' => (string) $data['book_id'],
            'total_cash' => $cash,
            'total_gpay' => $gpay,
            'total_collection' => $collection,
            'total_expenses' => $expenses,
            'net' => $collection - $expenses,
            'entries' => $entries->map(fn (DailyEntry $e) => $this->present($e))->values(),
        ]);
    }

    /** POST /daily-entries — record (or overwrite) one loan's collection for a day. */
    public function store(StoreDailyEntryRequest $request): JsonResponse
    {
        if ($deny = $this->denyBookAccess((string) $request->input('book_id'))) {
            return $deny;
        }

        $entry = DB::transaction(function () use ($request) {
            $entry = DailyEntry::updateOrCreate(
                ['loan_id' => $request->input('loan_id'), 'entry_date' => $request->input('entry_date')],
                [
                    'book_id' => $request->input('book_id'),
                    'amount' => $request->input('amount'),
                    'mode' => $request->input('mode'),
                ],
            );
            $this->recomputeLoanTotal($entry->loan_id);

            return $entry;
        });

        $entry->load(self::WITH);

        return $this->success($this->present($entry), 'Entry recorded', 201);
    }

    /** POST /daily-entries/bulk — upsert many loans' collections for one date. */
    public function bulk(BulkDailyEntryRequest $request): JsonResponse
    {
        if ($deny = $this->denyBookAccess((string) $request->input('book_id'))) {
            return $deny;
        }

        $bookId = $request->input('book_id');
        $date = $request->input('entry_date');

        $entries = DB::transaction(function () use ($request, $bookId, $date) {
            $saved = collect();
            foreach ($request->input('entries') as $item) {
                $saved->push(DailyEntry::updateOrCreate(
                    ['loan_id' => $item['loan_id'], 'entry_date' => $date],
                    ['book_id' => $bookId, 'amount' => $item['amount'], 'mode' => $item['mode']],
                ));
            }

            $saved->pluck('loan_id')->unique()->each(fn ($loanId) => $this->recomputeLoanTotal($loanId));

            return $saved;
        });

        $ids = $entries->pluck('id');
        $result = DailyEntry::with(self::WITH)->whereIn('id', $ids)->get()
            ->map(fn (DailyEntry $e) => $this->present($e));

        return $this->success($result, $result->count().' entries recorded', 201);
    }

    /** PUT /daily-entries/{id} — edit amount/mode (ledger cell). */
    public function update(UpdateDailyEntryRequest $request, string $id): JsonResponse
    {
        $entry = DailyEntry::find($id);
        if (! $entry) {
            return $this->error('Entry not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $entry->book_id)) {
            return $deny;
        }

        DB::transaction(function () use ($entry, $request) {
            $entry->update($request->only(['amount', 'mode']));
            $this->recomputeLoanTotal($entry->loan_id);
        });

        $entry->load(self::WITH);

        return $this->success($this->present($entry), 'Entry updated');
    }

    /** DELETE /daily-entries/{id} — clear a collection (ledger cell). */
    public function destroy(string $id): JsonResponse
    {
        $entry = DailyEntry::find($id);
        if (! $entry) {
            return $this->error('Entry not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $entry->book_id)) {
            return $deny;
        }

        DB::transaction(function () use ($entry) {
            $loanId = $entry->loan_id;
            $entry->delete();
            $this->recomputeLoanTotal($loanId);
        });

        return $this->success(null, 'Entry deleted');
    }

    /** Keep loans.total_collected in sync with the sum of its daily entries. */
    private function recomputeLoanTotal(string $loanId): void
    {
        $total = DailyEntry::where('loan_id', $loanId)->sum('amount');
        Loan::whereKey($loanId)->update(['total_collected' => $total]);
    }

    private function present(DailyEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'book_id' => (string) $entry->book_id,
            'loan_id' => (string) $entry->loan_id,
            'entry_date' => $entry->entry_date,
            'amount' => (float) $entry->amount,
            'mode' => $entry->mode,
            'created_at' => $entry->created_at,
            'updated_at' => $entry->updated_at,
            'loan_number' => $entry->loan?->loan_number,
            'customer_name' => $entry->loan?->customer?->name,
            'line' => $entry->loan?->line,
        ];
    }
}
