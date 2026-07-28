<?php

namespace App\Http\Requests\Hub;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class AssignHubWorkItemRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['operator_id', 'idempotency_key', 'note']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operator_id' => ['required', 'string', 'max:64'],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:160'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
