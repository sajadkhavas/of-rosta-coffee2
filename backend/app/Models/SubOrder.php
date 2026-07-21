<?php

namespace App\Models;

use App\Enums\SubOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SubOrder extends Model
{
    use HasUlids;

    protected $fillable = ['order_id','roastery_id','status','subtotal','shipping_total'];

    protected function casts(): array
    {
        return ['status' => SubOrderStatus::class,'subtotal' => 'integer','shipping_total' => 'integer'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function roastery(): BelongsTo { return $this->belongsTo(Roastery::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
