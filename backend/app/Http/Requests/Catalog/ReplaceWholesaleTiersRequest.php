<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Services\B2B\WholesaleTierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplaceWholesaleTiersRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['tiers']);
        $this->rejectUnexpectedNested(is_array($this->input('tiers')) ? $this->input('tiers') : [], ['min_weight_grams', 'unit_price', 'is_active'], 'tiers');
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tiers' => ['required', 'array', 'max:4'],
            'tiers.*.min_weight_grams' => ['required', 'integer', Rule::in(WholesaleTierService::DEFAULT_THRESHOLDS), 'distinct:strict'],
            'tiers.*.unit_price' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'tiers.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
