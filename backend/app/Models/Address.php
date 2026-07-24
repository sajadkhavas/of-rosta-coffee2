<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $title
 * @property string|null $recipient_name
 * @property string|null $recipient_mobile
 * @property string|null $province
 * @property string|null $city
 * @property string|null $address_line
 * @property string|null $postal_code
 * @property bool $is_default
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 */
final class Address extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'title',
        'recipient_name',
        'recipient_mobile',
        'province',
        'city',
        'address_line',
        'postal_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
