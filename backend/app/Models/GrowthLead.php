<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GrowthLead extends Model
{
    use HasUlids;

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_ROASTERY = 'roastery';
    public const TYPE_B2B = 'b2b';

    public const STATUS_LEAD = 'lead';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'partner_id',
        'type',
        'status',
        'dedupe_hash',
        'name',
        'mobile',
        'email',
        'company_name',
        'notes',
        'converted_user_id',
        'converted_roastery_id',
        'converted_order_id',
        'claimed_at',
        'contacted_at',
        'qualified_at',
        'converted_at',
        'lost_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'mobile' => 'encrypted',
            'email' => 'encrypted',
            'company_name' => 'encrypted',
            'notes' => 'encrypted',
            'claimed_at' => 'immutable_datetime',
            'contacted_at' => 'immutable_datetime',
            'qualified_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'lost_at' => 'immutable_datetime',
            'meta' => 'array',
        ];
    }

    /** @return list<string> */
    public static function types(): array
    {
        return [self::TYPE_CUSTOMER, self::TYPE_ROASTERY, self::TYPE_B2B];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(GrowthPartner::class, 'partner_id');
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function convertedRoastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class, 'converted_roastery_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}
