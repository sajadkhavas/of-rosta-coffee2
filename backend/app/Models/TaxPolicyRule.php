<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

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
