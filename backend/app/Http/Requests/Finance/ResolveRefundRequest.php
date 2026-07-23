<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolveRefundRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'outcome',
            'provider_reference',
            'failure_code',
            'message',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::in(['succeeded', 'failed', 'cancelled'])],
            'provider_reference' => ['nullable', 'string', 'max:190', 'required_if:outcome,succeeded'],
            'failure_code' => ['nullable', 'string', 'max:160', 'required_if:outcome,failed'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
