<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $settlement_allocation_id
 * @property string|null $order_id
 * @property string|null $sub_order_id
 * @property string|null $order_item_service_id
 * @property string $tax_code
 * @property int $taxable_amount
 * @property int $tax_amount
 * @property int|null $rate_basis_points
 * @property array<mixed> $calculation_snapshot
 */
final class OrderTaxLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'settlement_allocation_id',
        'order_id',
        'sub_order_id',
        'order_item_service_id',
        'tax_code',
        'jurisdiction',
        'taxable_amount',
        'tax_amount',
        'rate_basis_points',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'taxable_amount' => 'integer',
            'tax_amount' => 'integer',
            'rate_basis_points' => 'integer',
            'calculation_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<SettlementAllocation, $this> */
    public function settlementAllocation(): BelongsTo
    {
        return $this->belongsTo(SettlementAllocation::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<SubOrder, $this> */
    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    /** @return BelongsTo<OrderItemService, $this> */
    public function orderItemService(): BelongsTo
    {
        return $this->belongsTo(OrderItemService::class);
    }
}
