<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\ValidationException;

trait RejectsUnexpectedInput
{
    /**
     * @param  list<string>  $allowed
     */
    protected function rejectUnexpected(array $allowed): void
    {
        $unexpected = array_values(array_diff(array_keys($this->all()), $allowed));
        if ($unexpected === []) {
            return;
        }

        $messages = [];
        foreach ($unexpected as $key) {
            $messages[(string) $key] = ['این فیلد در قرارداد عمومی رستا تعریف نشده است.'];
        }

        throw ValidationException::withMessages($messages);
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  list<string>  $allowed
     */
    protected function rejectUnexpectedNested(array $items, array $allowed, string $prefix): void
    {
        $messages = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (array_diff(array_keys($item), $allowed) as $key) {
                $messages[$prefix.'.'.$index.'.'.$key] = [
                    'این فیلد در قرارداد عمومی رستا تعریف نشده است.',
                ];
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }
}
