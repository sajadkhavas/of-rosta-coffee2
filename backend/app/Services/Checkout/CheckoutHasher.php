<?php

namespace App\Services\Checkout;

final class CheckoutHasher
{
    /**
     * @param array<string, mixed> $payload
     */
    public function hash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_is_list($item)) {
                $value[$key] = array_map(
                    fn (mixed $child): mixed => is_array($child)
                        ? $this->canonicalize($child)
                        : $child,
                    $item,
                );
            } else {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
