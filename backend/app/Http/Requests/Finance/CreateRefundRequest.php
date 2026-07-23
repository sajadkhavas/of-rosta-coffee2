<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class CreateRefundRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['amount', 'reason', 'idempotency_key']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:160', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
