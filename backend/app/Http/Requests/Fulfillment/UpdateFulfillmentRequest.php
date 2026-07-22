<?php

namespace App\Http\Requests\Fulfillment;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFulfillmentRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'status',
            'reason',
            'carrier',
            'tracking_code',
            'internal_note',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    'accepted',
                    'rejected',
                    'preparing',
                    'ready_to_ship',
                    'shipped',
                    'delivered',
                ]),
            ],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:status,rejected'],
            'carrier' => ['nullable', 'string', 'max:120', 'required_if:status,shipped'],
            'tracking_code' => [
                'nullable',
                'string',
                'min:3',
                'max:200',
                'regex:/^[A-Za-z0-9\x{0600}-\x{06FF}._\-\/ ]+$/u',
                'required_if:status,shipped',
            ],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
