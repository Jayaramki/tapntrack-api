<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivedLoan extends Model
{
    use HasUuids;

    protected $table = 'archived_loans';

    protected $fillable = [
        'book_id',
        'customer_id',
        'loan_number',
        'loan_amount',
        'interest_amount',
        'loan_type',
        'line',
        'issued_date',
        'completed_date',
        'total_collected',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'loan_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_collected' => 'decimal:2',
            'issued_date' => 'date:Y-m-d',
            'completed_date' => 'date:Y-m-d',
            'archived_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
