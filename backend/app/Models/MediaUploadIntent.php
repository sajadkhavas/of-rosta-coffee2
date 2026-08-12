<?php

namespace App\Models;

use App\Enums\MediaUploadStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $roastery_id
 * @property string|null $user_id
 * @property string|null $media_asset_id
 * @property string|null $disk
 * @property string|null $object_key
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property string|null $checksum_sha256
 * @property MediaUploadStatus $status
 * @property int $processing_attempts
 * @property string|null $alt_text
 * @property string|null $detected_mime_type
 * @property int|null $actual_size_bytes
 * @property string|null $actual_checksum_sha256
 * @property int|null $detected_width
 * @property int|null $detected_height
 * @property string|null $variant_version
 * @property bool $failure_retryable
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $processing_started_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $failed_at
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Roastery|null $roastery
 * @property-read User|null $user
 * @property-read MediaAsset|null $mediaAsset
 */
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
        'processing_attempts',
        'alt_text',
        'detected_mime_type',
        'actual_size_bytes',
        'actual_checksum_sha256',
        'detected_width',
        'detected_height',
        'variant_version',
        'failure_retryable',
        'expires_at',
        'processing_started_at',
        'ready_at',
        'rejected_at',
        'completed_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'status' => MediaUploadStatus::class,
            'processing_attempts' => 'integer',
            'actual_size_bytes' => 'integer',
            'detected_width' => 'integer',
            'detected_height' => 'integer',
            'failure_retryable' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'processing_started_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }
}
