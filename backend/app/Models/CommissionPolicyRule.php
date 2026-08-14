<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CommissionPolicyRule extends Model
{
    use HasUlids;

    protected $fillable = [
        'commission_policy_id', 'code', 'component', 'owner_type', 'rate_basis_points',
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
        $guard = static function (CommissionPolicyRule $rule): void {
            if ($rule->policy()->value('status') === 'published') {
                throw new LogicException('Rules of a published commission policy are immutable.');
            }
        };
        self::saving($guard);
        self::deleting($guard);
    }

    /** @return BelongsTo<CommissionPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(CommissionPolicy::class, 'commission_policy_id');
    }
}
