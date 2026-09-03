<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PartnerAttribution extends Model
{
    use HasUlids;

    protected $fillable = [
        'partner_id', 'lead_id', 'subject_type', 'subject_id', 'source',
        'attributed_at', 'locked_at', 'converted_at', 'context',
    ];

    protected function casts(): array
    {
        return [
            'attributed_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'context' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(GrowthPartner::class, 'partner_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(GrowthLead::class, 'lead_id');
    }
}
