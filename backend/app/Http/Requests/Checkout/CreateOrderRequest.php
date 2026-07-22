<?php

namespace App\Http\Requests\Checkout;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class CreateOrderRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['quote_id', 'idempotency_key', 'notes']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'string', 'max:200'],
            'idempotency_key' => ['required', 'string', 'min:24', 'max:160', 'regex:/^[A-Za-z0-9:_-]+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
