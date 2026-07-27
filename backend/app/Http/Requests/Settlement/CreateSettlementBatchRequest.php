<?php

namespace App\Http\Requests\Settlement;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class CreateSettlementBatchRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['roastery_id', 'currency', 'idempotency_key']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roastery_id' => ['required', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'in:IRR'],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:160'],
        ];
    }
}
