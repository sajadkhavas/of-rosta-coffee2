<?php

namespace App\Models;

use App\Enums\CarrierEventType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CarrierWebhookReceipt extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'shipment_leg_id',
        'carrier',
        'tracking_code',
        'event_type',
        'occurred_at',
        'payload_hash',
        'signature_version',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => CarrierEventType::class,
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
        ];
    }

    public function shipmentLeg(): BelongsTo
    {
        return $this->belongsTo(ShipmentLeg::class);
    }
}
