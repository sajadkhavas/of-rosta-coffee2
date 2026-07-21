<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShippingRule extends Model
{
    use HasUlids;

    protected $fillable = ['roastery_id','province','city','base_cost','free_over','priority','is_active'];

    protected function casts(): array
    {
        return ['base_cost' => 'integer','free_over' => 'integer','priority' => 'integer','is_active' => 'boolean'];
    }

    public function roastery(): BelongsTo { return $this->belongsTo(Roastery::class); }
}
