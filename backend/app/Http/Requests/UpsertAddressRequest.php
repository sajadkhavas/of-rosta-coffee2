<?php

namespace App\Http\Requests;

use App\Support\IranMobile;
use Illuminate\Foundation\Http\FormRequest;

final class UpsertAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'title' => $this->input('title') === null
                ? null
                : trim((string) $this->input('title')),
            'recipient_name' => trim((string) $this->input('recipient_name')),
            'recipient_mobile' => IranMobile::normalize(
                (string) $this->input('recipient_mobile'),
            ),
            'province' => trim((string) $this->input('province')),
            'city' => trim((string) $this->input('city')),
            'address_line' => trim((string) $this->input('address_line')),
            'postal_code' => $this->input('postal_code') === null
                ? null
                : trim(IranMobile::normalizeDigits(
                    (string) $this->input('postal_code'),
                )),
        ];

        if ($this->exists('is_default')) {
            $payload['is_default'] = filter_var(
                $this->input('is_default'),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            );
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'min:1', 'max:120'],
            'recipient_mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'province' => ['required', 'string', 'min:1', 'max:120'],
            'city' => ['required', 'string', 'min:1', 'max:120'],
            'address_line' => ['required', 'string', 'min:1', 'max:1000'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}
