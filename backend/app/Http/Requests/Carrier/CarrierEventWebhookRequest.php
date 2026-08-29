<?php

namespace App\Http\Requests\Carrier;

use App\Enums\CarrierEventType;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CarrierEventWebhookRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'carrier',
            'tracking_code',
            'event_type',
            'occurred_at',
            'evidence_reference',
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
            'event_type' => ['required', 'string', Rule::enum(CarrierEventType::class)],
            'occurred_at' => ['required', 'date'],
            'evidence_reference' => ['nullable', 'string', 'max:500'],
        ];
    }
}
