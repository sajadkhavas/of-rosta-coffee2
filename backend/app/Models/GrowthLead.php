<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GrowthLead extends Model
{
    use HasUlids;

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
