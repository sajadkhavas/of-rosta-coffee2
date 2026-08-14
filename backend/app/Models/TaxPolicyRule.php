<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string $tax_policy_id
 * @property string $code
 * @property string $component
 * @property string $jurisdiction
 * @property int $rate_basis_points
 * @property int $priority
 * @property array<string, mixed>|null $applicability
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TaxPolicy|null $policy
 */
final class TaxPolicyRule extends Model
{
    use HasUlids;

    protected $fillable = [
        'tax_policy_id', 'code', 'component', 'jurisdiction', 'rate_basis_points',
        'priority', 'applicability',
    ];

    protected function casts(): array
    {
        return [
            'rate_basis_points' => 'integer',
            'priority' => 'integer',
            'applicability' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $guard = static function (TaxPolicyRule $rule): void {
            if ($rule->policy()->value('status') === 'published') {
                throw new LogicException('Rules of a published tax policy are immutable.');
            }
        };
        self::saving($guard);
        self::deleting($guard);
    }

    /** @return BelongsTo<TaxPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(TaxPolicy::class, 'tax_policy_id');
    }
}
