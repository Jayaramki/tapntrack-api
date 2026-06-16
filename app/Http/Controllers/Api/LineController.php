<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreLineRequest;
use App\Http\Requests\UpdateLineRequest;
use App\Models\ArchivedLoan;
use App\Models\Line;
use App\Models\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'uuid', 'exists:books,id'],
        ]);

        if ($deny = $this->denyBookAccess((string) $data['book_id'])) {
            return $deny;
        }

        $lines = Line::where('book_id', $data['book_id'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->success($lines);
    }

    public function store(StoreLineRequest $request): JsonResponse
    {
        if ($deny = $this->denyBookAccess((string) $request->input('book_id'))) {
            return $deny;
        }

        $line = Line::create([
            'book_id' => $request->input('book_id'),
            'name' => $request->input('name'),
            'color' => $request->input('color', '#546E7A'),
            'is_active' => true,
        ]);

        return $this->success($line, 'Line created', 201);
    }

    public function update(UpdateLineRequest $request, string $id): JsonResponse
    {
        $line = Line::find($id);
        if (! $line) {
            return $this->error('Line not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $line->book_id)) {
            return $deny;
        }

        $line->update($request->only(['name', 'color', 'is_active']));

        return $this->success($line, 'Line updated');
    }

    /**
     * A line referenced by ANY loan (active, soft-deleted, or archived) cannot be
     * deleted — only deactivated. Otherwise it is removed.
     */
    public function destroy(string $id): JsonResponse
    {
        $line = Line::find($id);
        if (! $line) {
            return $this->error('Line not found', [], 404);
        }
        if ($deny = $this->denyBookAccess((string) $line->book_id)) {
            return $deny;
        }

        $inUse = Loan::where('book_id', $line->book_id)->where('line', $line->name)->exists()
            || ArchivedLoan::where('book_id', $line->book_id)->where('line', $line->name)->exists();

        if ($inUse) {
            return $this->error('This line is used by existing loans and cannot be deleted. Deactivate it instead.', [], 409);
        }

        $line->delete();

        return $this->success(null, 'Line deleted');
    }
}
