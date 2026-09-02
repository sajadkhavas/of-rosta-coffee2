<?php

namespace App\Http\Requests\Settlement;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewRoasterySettlementProfileRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['decision', 'note']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['verified', 'rejected'])],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,rejected'],
        ];
    }
}
