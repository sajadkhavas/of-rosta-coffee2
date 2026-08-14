<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $user_id
 * @property string $order_id
 * @property string $order_item_id
 * @property string $product_id
 * @property string $roastery_id
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property ReviewStatus $status
 * @property bool $is_verified_purchase
 * @property string|null $moderated_by
 * @property CarbonImmutable|null $moderated_at
 * @property string|null $moderation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Order|null $order
 * @property-read OrderItem|null $orderItem
 * @property-read Product|null $product
 * @property-read Roastery|null $roastery
 * @property-read User|null $moderator
 * @property-read ReviewReply|null $reply
 */
final class Review extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'order_id', 'order_item_id', 'product_id', 'roastery_id', 'rating', 'title', 'body', 'status', 'is_verified_purchase', 'moderated_by', 'moderated_at', 'moderation_reason'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'status' => ReviewStatus::class, 'is_verified_purchase' => 'boolean', 'moderated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo { return $this->belongsTo(Roastery::class); }
    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo { return $this->belongsTo(User::class, 'moderated_by'); }
    /** @return HasOne<ReviewReply, $this> */
    public function reply(): HasOne { return $this->hasOne(ReviewReply::class); }
    /** @return HasMany<ReviewReport, $this> */
    public function reports(): HasMany { return $this->hasMany(ReviewReport::class); }
}
