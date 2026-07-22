<?php

namespace App\Models;

use App\Enums\MediaUploadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MediaUploadIntent extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'user_id',
        'media_asset_id',
        'disk',
        'object_key',
        'original_filename',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'status',
        'expires_at',
        'completed_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'status' => MediaUploadStatus::class,
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
