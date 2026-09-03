<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $id
 * @property string $version
 * @property string $status
 * @property string $basis
 * @property int $basis_points
 * @property string|null $rounding_mode
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string|null $checksum
 * @property string|null $notes
 * @property string|null $created_by_id
 */
final class PartnerCommissionPolicy extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_RETIRED = 'retired';

    public const BASIS_PLATFORM_REVENUE = 'platform_revenue';

    protected $fillable = [
        'version', 'status', 'basis', 'basis_points', 'rounding_mode', 'effective_from',
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
        self::updating(function (self $policy): void {
            if ($policy->getOriginal('status') !== self::STATUS_DRAFT) {
                throw new LogicException('Published partner commission policies are immutable.');
            }
        });

        self::deleting(function (self $policy): void {
            if ($policy->status !== self::STATUS_DRAFT) {
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
