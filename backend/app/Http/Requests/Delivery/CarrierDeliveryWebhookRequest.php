<?php

namespace App\Http\Requests\Delivery;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class CarrierDeliveryWebhookRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'carrier',
            'tracking_code',
            'idempotency_key',
            'proof_type',
            'proof_payload',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrier' => ['required', 'string', 'max:120'],
            'tracking_code' => ['required', 'string', 'max:200'],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:160'],
            'proof_type' => ['required', 'string', 'in:carrier_scan,signature_reference,photo_reference'],
            'proof_payload' => ['nullable', 'array', 'max:20'],
            'proof_payload.reference' => ['nullable', 'string', 'max:500'],
            'proof_payload.occurred_at' => ['nullable', 'date'],
        ];
    }
}
