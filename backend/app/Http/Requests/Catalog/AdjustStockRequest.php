<?php

namespace App\Http\Requests\Catalog;

use App\Enums\StockReason;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdjustStockRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'delta',
            'reason',
            'roast_batch_id',
            'idempotency_key',
            'metadata',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delta' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', Rule::enum(StockReason::class)],
            'roast_batch_id' => ['nullable', 'string', 'max:200'],
            'idempotency_key' => ['nullable', 'string', 'min:24', 'max:160', 'regex:/^[A-Za-z0-9:_-]+$/'],
            'metadata' => ['sometimes', 'array', 'max:30'],
        ];
    }
}
