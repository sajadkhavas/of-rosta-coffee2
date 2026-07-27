<?php

namespace App\Http\Requests\Settlement;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetSettlementHoldRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['action', 'code', 'note']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['hold', 'release'])],
            'code' => ['nullable', 'string', 'max:64', 'required_if:action,hold'],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
