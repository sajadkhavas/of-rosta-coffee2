<?php

namespace App\Http\Requests\Settlement;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResolveSettlementBatchRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['action', 'payout_reference', 'failure_code', 'failure_message']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['process', 'paid', 'failed'])],
            'payout_reference' => ['nullable', 'string', 'max:190', 'required_if:action,paid'],
            'failure_code' => ['nullable', 'string', 'max:160', 'required_if:action,failed'],
            'failure_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
