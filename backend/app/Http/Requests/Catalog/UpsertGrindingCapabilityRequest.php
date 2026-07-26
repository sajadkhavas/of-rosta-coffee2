<?php

namespace App\Http\Requests\Catalog;

use App\Enums\FeeMode;
use App\Enums\GrindingAvailability;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertGrindingCapabilityRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'availability',
            'fee_mode',
            'fee_amount',
            'preparation_minutes',
            'capacity_per_day',
            'supported_weights',
            'grinding_profile_ids',
            'is_active',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'availability' => ['required', Rule::enum(GrindingAvailability::class)],
            'fee_mode' => ['required', Rule::enum(FeeMode::class)],
            'fee_amount' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
            'preparation_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'capacity_per_day' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'supported_weights' => ['required', 'array', 'max:5'],
            'supported_weights.*' => ['integer', Rule::in([50, 100, 250, 500, 1000])],
            'grinding_profile_ids' => ['required', 'array', 'max:20'],
            'grinding_profile_ids.*' => [
                'string',
                'max:200',
                Rule::exists('grinding_profiles', 'id')->where('is_active', true),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
