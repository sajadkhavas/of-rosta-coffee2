<?php

namespace App\Http\Requests\Fulfillment;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolveFulfillmentIncidentRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['resolution', 'note', 'extend_sla_hours']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', Rule::in([
                'resume_fulfillment',
                'cancel_and_refund',
            ])],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
            'extend_sla_hours' => [
                'nullable',
                'integer',
                'min:0',
                'max:168',
                'required_if:resolution,resume_fulfillment',
            ],
        ];
    }
}
