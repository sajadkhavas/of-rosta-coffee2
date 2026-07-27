<?php

namespace App\Http\Requests\Fulfillment;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReportFulfillmentIncidentRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['code', 'description', 'severity']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', Rule::in([
                'inventory_mismatch',
                'equipment_failure',
                'closure',
                'quality_hold',
                'carrier_disruption',
                'other',
            ])],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'severity' => ['sometimes', 'string', Rule::in(['medium', 'high', 'critical'])],
        ];
    }
}
