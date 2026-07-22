<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ContentEntry::class, 'content_entry_id');
    }
}
