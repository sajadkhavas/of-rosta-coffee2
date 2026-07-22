<?php

namespace App\Http\Requests\Content;

use App\Enums\ContentStatus;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetContentStatusRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['status']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ContentStatus::class)]];
    }
}
