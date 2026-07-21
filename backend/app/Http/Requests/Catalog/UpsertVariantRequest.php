<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertVariantRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'sku',
            'weight_grams',
            'price',
            'compare_at_price',
            'is_active',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = $this->route('variantId');
        $productId = (string) $this->route('productId');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'sku' => [
                $required,
                'string',
                'min:1',
                'max:120',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ],
            'weight_grams' => [
                $required,
                'integer',
                Rule::in(config('rosta.whole_bean_weights')),
                Rule::unique('product_variants', 'weight_grams')
                    ->where('product_id', $productId)
                    ->ignore($variantId),
            ],
            'price' => [$required, 'integer', 'min:0', 'max:9007199254740991'],
            'compare_at_price' => ['nullable', 'integer', 'gte:price', 'max:9007199254740991'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
