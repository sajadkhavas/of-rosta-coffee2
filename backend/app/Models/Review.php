<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Review extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'order_id', 'order_item_id', 'product_id', 'roastery_id', 'rating', 'title', 'body', 'status', 'is_verified_purchase', 'moderated_by', 'moderated_at', 'moderation_reason'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'status' => ReviewStatus::class, 'is_verified_purchase' => 'boolean', 'moderated_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function roastery(): BelongsTo { return $this->belongsTo(Roastery::class); }
    public function moderator(): BelongsTo { return $this->belongsTo(User::class, 'moderated_by'); }
    public function reply(): HasOne { return $this->hasOne(ReviewReply::class); }
    public function reports(): HasMany { return $this->hasMany(ReviewReport::class); }
}
