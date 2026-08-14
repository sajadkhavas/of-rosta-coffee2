<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $id
 * @property int $version
 * @property string $status
 * @property string $currency
 * @property string $rounding_mode
 * @property CarbonImmutable|null $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string $created_by
 * @property string|null $submitted_by
 * @property string|null $published_by
 * @property string|null $checksum
 * @property string $change_reason
 * @property-read Collection<int, TaxPolicyRule> $rules
 */
final class TaxPolicy extends Model
{
    use HasUlids;

    protected $fillable = [
        'version', 'status', 'currency', 'rounding_mode', 'effective_from', 'effective_to',
        'created_by', 'submitted_by', 'published_by', 'submitted_at', 'published_at',
        'checksum', 'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (TaxPolicy $policy): void {
            if ($policy->getOriginal('status') === 'published') {
                throw new LogicException('Published tax policies are immutable.');
            }
        });
        static::deleting(function (TaxPolicy $policy): void {
            if ($policy->status === 'published') {
                throw new LogicException('Published tax policies cannot be deleted.');
            }
        });
    }

    /** @return HasMany<TaxPolicyRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(TaxPolicyRule::class)->orderBy('priority')->orderBy('code');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
