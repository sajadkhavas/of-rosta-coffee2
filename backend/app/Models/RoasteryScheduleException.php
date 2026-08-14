<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RoasteryScheduleException extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'local_date',
        'is_closed',
        'opens_at',
        'closes_at',
        'public_reason',
    ];

    protected function casts(): array
    {
        return ['local_date' => 'immutable_date', 'is_closed' => 'boolean'];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
