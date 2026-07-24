<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $content_entry_id
 * @property string|null $relation_type
 * @property string|null $target_type
 * @property string|null $target_key
 * @property string|null $anchor_text
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ContentEntry|null $entry
 */
final class ContentRelation extends Model
{
    use HasUlids;

    protected $fillable = [
        'content_entry_id',
        'relation_type',
        'target_type',
        'target_key',
        'anchor_text',
        'position',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /** @return BelongsTo<ContentEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ContentEntry::class, 'content_entry_id');
    }
}
