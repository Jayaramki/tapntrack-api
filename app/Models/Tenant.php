<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    /**
     * Fixed sentinel for the default tenant that pre-SaaS data is folded under.
     * Mirrors the migration backfill + book sentinel pattern.
     */
    public const DEFAULT_TENANT_ID = '11111111-1111-1111-1111-111111111111';

    protected $fillable = [
        'slug',
        'name',
        'owner_name',
        'email',
        'phone',
        'status',
        'plan',
        'trial_ends_at',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'is_deleted' => 'boolean',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
