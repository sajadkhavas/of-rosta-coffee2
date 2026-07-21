<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoastBatchRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['batch_code', 'roasted_at', 'available_from', 'is_active']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_code' => [
                'required',
                'string',
                'min:1',
                'max:120',
                Rule::unique('roast_batches', 'batch_code')
                    ->where('product_id', (string) $this->route('productId')),
            ],
            'roasted_at' => ['required', 'date'],
            'available_from' => ['nullable', 'date', 'after_or_equal:roasted_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
