<?php

namespace App\Http\Requests\Catalog;

use App\Enums\RoasteryStatus;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetRoasteryStatusRequest extends FormRequest
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
        return ['status' => ['required', Rule::enum(RoasteryStatus::class)]];
    }
}
