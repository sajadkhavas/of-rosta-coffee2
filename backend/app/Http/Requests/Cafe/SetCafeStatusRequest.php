<?php

namespace App\Http\Requests\Cafe;

use App\Enums\CafeStatus;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetCafeStatusRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void { $this->rejectUnexpected(['status', 'review_note']); }
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CafeStatus::class)],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
