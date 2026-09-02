<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $roastery_id
 * @property string $entity_type
 * @property string $legal_name
 * @property string $account_holder_name
 * @property string $iban
 * @property string $iban_last4
 * @property string $status
 * @property string|null $submitted_by_id
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $reviewed_by_id
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property-read Roastery $roastery
 * @property-read User|null $submitter
 * @property-read User|null $reviewer
 */
final class RoasterySettlementProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'entity_type',
        'legal_name',
        'account_holder_name',
        'iban',
        'iban_last4',
        'status',
        'submitted_by_id',
        'submitted_at',
        'reviewed_by_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'legal_name' => 'encrypted',
            'account_holder_name' => 'encrypted',
            'iban' => 'encrypted',
            'review_note' => 'encrypted',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function maskedIban(): string
    {
        return 'IR••••••••••••••••••••'.$this->iban_last4;
    }
}
