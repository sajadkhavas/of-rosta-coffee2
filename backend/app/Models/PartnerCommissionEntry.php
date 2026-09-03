<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PartnerCommissionEntry extends Model
{
    use HasUlids;

    public const TYPE_ACCRUAL = 'accrual';
    public const TYPE_REVERSAL = 'reversal';

    protected $fillable = [
        'partner_id',
        'attribution_id',
        'policy_id',
        'order_id',
        'entry_type',
        'reversal_of_id',
        'status',
        'attributed_gmv_amount',
        'platform_revenue_amount',
        'commission_amount',
        'currency',
        'idempotency_key',
        'source_type',
        'source_id',
        'financial_snapshot',
        'earned_at',
        'approved_at',
        'paid_at',
        'recorded_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'attributed_gmv_amount' => 'integer',
            'platform_revenue_amount' => 'integer',
            'commission_amount' => 'integer',
            'financial_snapshot' => 'array',
            'earned_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
            'reason' => 'encrypted',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(GrowthPartner::class, 'partner_id');
    }

    public function attribution(): BelongsTo
    {
        return $this->belongsTo(PartnerAttribution::class, 'attribution_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(PartnerCommissionPolicy::class, 'policy_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
