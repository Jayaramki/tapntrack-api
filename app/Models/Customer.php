<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $fillable = [
        'book_id',
        'name',
        'father_name',
        'phone',
        'address',
        'profession',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'book_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
