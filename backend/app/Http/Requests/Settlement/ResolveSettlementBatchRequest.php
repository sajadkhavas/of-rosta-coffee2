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
        $this->rejectUnexpected([
            'action',
            'payout_reference',
            'payout_method',
            'payout_evidence',
            'failure_code',
            'failure_message',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['process', 'paid', 'failed', 'review'])],
            'payout_reference' => ['nullable', 'string', 'max:190', 'required_if:action,paid'],
            'payout_method' => [
                'nullable',
                'string',
                Rule::in(['manual_bank_transfer', 'provider_dashboard']),
                'required_if:action,paid',
            ],
            'payout_evidence' => ['nullable', 'array', 'required_if:action,paid'],
            'payout_evidence.source' => ['required_with:payout_evidence', 'string', 'max:80'],
            'payout_evidence.executed_at' => ['required_with:payout_evidence', 'date'],
            'payout_evidence.amount' => ['required_with:payout_evidence', 'integer', 'min:1'],
            'payout_evidence.currency' => ['required_with:payout_evidence', 'string', 'size:3'],
            'payout_evidence.note' => ['nullable', 'string', 'max:1000'],
            'failure_code' => [
                'nullable',
                'string',
                'max:160',
                Rule::requiredIf(fn (): bool => in_array($this->input('action'), ['failed', 'review'], true)),
            ],
            'failure_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
