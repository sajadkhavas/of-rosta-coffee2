<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CafeMembership extends Model
{
    use HasUlids;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';

    protected $fillable = [
        'cafe_id',
        'user_id',
        'role',
        'is_active',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
