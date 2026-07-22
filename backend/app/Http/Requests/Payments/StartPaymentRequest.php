<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class StartPaymentRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['order_id', 'idempotency_key']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'idempotency_key' => ['required', 'string', 'min:24', 'max:160', 'regex:/^[A-Za-z0-9:_-]+$/'],
        ];
    }
}
