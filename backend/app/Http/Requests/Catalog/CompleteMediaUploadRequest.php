<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class CompleteMediaUploadRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['alt', 'width', 'height', 'blur_data_url']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alt' => ['required', 'string', 'max:300'],
            'width' => ['required', 'integer', 'min:1', 'max:20000'],
            'height' => ['required', 'integer', 'min:1', 'max:20000'],
            'blur_data_url' => [
                'nullable',
                'string',
                'max:250000',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! str_starts_with($value, 'data:image/')) {
                        $fail('Blur placeholder باید Data URL تصویری باشد.');
                    }
                },
            ],
        ];
    }
}
