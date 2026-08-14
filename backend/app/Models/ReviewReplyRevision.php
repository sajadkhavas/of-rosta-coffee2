<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReviewReplyRevision extends Model
{
    use HasUlids;
    public $timestamps = false;
    protected $fillable = ['reply_id', 'editor_id', 'body', 'previous_status', 'created_at'];
    protected function casts(): array { return ['created_at' => 'immutable_datetime']; }
    public function reply(): BelongsTo { return $this->belongsTo(ReviewReply::class, 'reply_id'); }
}
