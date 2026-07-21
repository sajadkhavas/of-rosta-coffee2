<?php

namespace App\Http\Requests;

use App\Support\IranMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => IranMobile::normalize((string) $this->input('mobile')),
            'purpose' => trim((string) $this->input('purpose')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'purpose' => [
                'required',
                'string',
                Rule::in(['login', 'register', 'verify_mobile']),
            ],
        ];
    }
}
