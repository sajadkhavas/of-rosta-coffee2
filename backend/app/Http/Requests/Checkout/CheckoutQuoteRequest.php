<?php

namespace App\Http\Requests\Checkout;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class CheckoutQuoteRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['items', 'address_id', 'coupon_code']);
        $this->rejectUnexpectedNested(
            is_array($this->input('items')) ? $this->input('items') : [],
            ['variant_id', 'quantity', 'grinding_profile_id'],
            'items',
        );
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variant_id' => ['required', 'string', 'max:200', 'distinct:strict'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.grinding_profile_id' => ['nullable', 'string', 'max:200'],
            'address_id' => ['required', 'string', 'max:200'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}
