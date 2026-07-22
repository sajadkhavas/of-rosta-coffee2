<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class NotificationTemplate extends Model
{
    use HasUlids;

    protected $fillable = [
        'key',
        'channel',
        'body',
        'provider_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $message = $this->body;

        foreach ($payload as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $message = str_replace(
                '{{'.$key.'}}',
                trim((string) $value),
                $message,
            );
        }

        return trim($message);
    }
}
