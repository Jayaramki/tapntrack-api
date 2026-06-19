<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'label',
        'max_active_loans',
        'max_users',
        'max_books',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_active_loans' => 'integer',
            'max_users' => 'integer',
            'max_books' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
