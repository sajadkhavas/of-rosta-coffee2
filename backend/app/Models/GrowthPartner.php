<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GrowthPartner extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'code',
        'channel',
        'status',
        'display_name',
        'terms_version',
        'terms_accepted_at',
        'activated_at',
        'suspended_at',
        'reviewed_by_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'review_note' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(GrowthLead::class, 'partner_id');
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(PartnerAttribution::class, 'partner_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommissionEntry::class, 'partner_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->terms_version !== null
            && $this->terms_accepted_at !== null;
    }
}
