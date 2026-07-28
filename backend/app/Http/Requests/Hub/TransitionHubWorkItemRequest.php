<?php

namespace App\Http\Requests\Hub;

use App\Enums\HubWorkItemAction;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionHubWorkItemRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['action', 'idempotency_key', 'evidence']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(array_values(array_filter(array_column(HubWorkItemAction::cases(), 'value'), static fn (string $value): bool => ! in_array($value, ['created', 'assign'], true))))],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:160'],
            'evidence' => ['nullable', 'array', 'max:20'],
            'evidence.reference' => ['nullable', 'string', 'max:240'],
            'evidence.note' => ['nullable', 'string', 'max:2000'],
            'evidence.measurements' => ['nullable', 'array', 'max:20'],
        ];
    }
}
