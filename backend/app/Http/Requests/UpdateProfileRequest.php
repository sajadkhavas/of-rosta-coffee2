<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('name')) {
            $payload['name'] = $this->input('name') === null
                ? null
                : trim((string) $this->input('name'));
        }

        if ($this->exists('email')) {
            $payload['email'] = $this->input('email') === null
                ? null
                : strtolower(trim((string) $this->input('email')));
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'nullable',
                'string',
                'email:rfc',
                'max:254',
                Rule::unique('users', 'email')->ignore($userId),
            ],
        ];
    }
}
