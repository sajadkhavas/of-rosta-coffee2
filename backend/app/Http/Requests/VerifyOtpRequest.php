<?php

namespace App\Http\Requests;

use App\Support\IranMobile;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'request_id' => trim((string) $this->input('request_id')),
            'code' => trim(IranMobile::normalizeDigits((string) $this->input('code'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'request_id' => [
                'required',
                'string',
                'max:200',
                'regex:/^[A-Za-z0-9._:-]+$/',
            ],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
