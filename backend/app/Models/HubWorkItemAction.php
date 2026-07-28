<?php

namespace App\Models;

use App\Enums\HubWorkItemAction as HubAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string $hub_work_item_id
 * @property string|null $actor_id
 * @property HubAction $action
 * @property string|null $from_status
 * @property string $to_status
 * @property string $idempotency_key
 * @property string $public_label
 * @property array<mixed>|null $private_evidence
 * @property CarbonImmutable $occurred_at
 * @property-read HubWorkItem|null $workItem
 * @property-read User|null $actor
 */
final class HubWorkItemAction extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'hub_work_item_id', 'actor_id', 'action', 'from_status', 'to_status',
        'idempotency_key', 'public_label', 'private_evidence', 'occurred_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => HubAction::class,
            'private_evidence' => 'encrypted:array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Hub work item actions are append-only.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Hub work item actions are append-only.');
        });
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(HubWorkItem::class, 'hub_work_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
