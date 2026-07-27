<?php

namespace App\Http\Requests\Delivery;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmDeliveryRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'idempotency_key',
            'proof_type',
            'proof_payload',
            'customer_note',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:12', 'max:160'],
            'proof_type' => ['required', 'string', Rule::in([
                'customer_acknowledgement',
                'carrier_scan',
                'signature_reference',
                'photo_reference',
                'administrator_override',
            ])],
            'proof_payload' => ['nullable', 'array', 'max:20'],
            'proof_payload.reference' => ['nullable', 'string', 'max:500'],
            'proof_payload.occurred_at' => ['nullable', 'date'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
