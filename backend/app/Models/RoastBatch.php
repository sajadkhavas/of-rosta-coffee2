<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RoastBatch extends Model
{
    use HasUlids;

    protected $fillable = ['product_id','batch_code','roasted_at','available_from','is_active'];

    protected function casts(): array
    {
        return ['roasted_at' => 'immutable_datetime','available_from' => 'immutable_datetime','is_active' => 'boolean'];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function stockLedgerEntries(): HasMany { return $this->hasMany(StockLedgerEntry::class); }
}
