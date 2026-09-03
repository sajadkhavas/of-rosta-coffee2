<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class PartnerCommissionPolicy extends Model
{
    use HasUlids;

    protected $fillable = [
        'version', 'status', 'basis', 'basis_points', 'effective_from',
        'effective_to', 'checksum', 'notes', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'basis_points' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $policy): void {
            if ($policy->getOriginal('status') !== 'draft') {
                throw new LogicException('Published partner commission policies are immutable.');
            }
        });

        static::deleting(function (self $policy): void {
            if ($policy->status !== 'draft') {
                throw new LogicException('Published partner commission policies cannot be deleted.');
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PartnerCommissionEntry::class, 'policy_id');
    }
}
