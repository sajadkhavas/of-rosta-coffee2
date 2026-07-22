<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateInquiryRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'type',
            'name',
            'mobile',
            'email',
            'order_number',
            'message',
            'website',
        ]);

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'mobile' => trim((string) $this->input('mobile')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'order_number' => strtoupper(trim((string) $this->input('order_number'))),
            'message' => trim((string) $this->input('message')),
            'website' => trim((string) $this->input('website')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    'support',
                    'order_issue',
                    'roastery_onboarding',
                    'corporate_purchase',
                    'content_correction',
                    'privacy_request',
                ]),
            ],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'mobile' => [
                'nullable',
                'required_without:email',
                'string',
                'regex:/^(?:\+98|0098|98|0)?9\d{9}$/',
            ],
            'email' => ['nullable', 'required_without:mobile', 'email:rfc', 'max:254'],
            'order_number' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[A-Z0-9._-]+$/',
                'required_if:type,order_issue',
            ],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }
}
