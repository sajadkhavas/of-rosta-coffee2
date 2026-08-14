<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RoasteryWeeklyHour extends Model
{
    use HasUlids;

    protected $fillable = ['roastery_id', 'weekday', 'opens_at', 'closes_at', 'is_closed'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'is_closed' => 'boolean'];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
