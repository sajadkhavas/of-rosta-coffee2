<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $hub_id
 * @property string $province
 * @property string $city
 * @property int $inbound_shipping_fee
 * @property int $outbound_shipping_fee
 * @property int $priority
 * @property bool $is_active
 * @property-read RostaHub|null $hub
 */
final class RostaHubServiceZone extends Model
{
    use HasUlids;

    protected $fillable = [
        'hub_id',
        'province',
        'city',
        'inbound_shipping_fee',
        'outbound_shipping_fee',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'inbound_shipping_fee' => 'integer',
            'outbound_shipping_fee' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<RostaHub, $this> */
    public function hub(): BelongsTo
    {
        return $this->belongsTo(RostaHub::class, 'hub_id');
    }
}
