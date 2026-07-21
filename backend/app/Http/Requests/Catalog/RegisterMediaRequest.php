<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterMediaRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['alt', 'width', 'height', 'blur_data_url', 'sources']);
        $this->rejectUnexpectedNested(
            is_array($this->input('sources')) ? $this->input('sources') : [],
            ['url', 'width', 'format'],
            'sources',
        );
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
            'sources' => ['required', 'array', 'min:1', 'max:12'],
            'sources.*.url' => ['required', 'url:https', 'max:2000'],
            'sources.*.width' => ['required', 'integer', 'min:1', 'max:20000'],
            'sources.*.format' => ['required', Rule::in(['avif', 'webp', 'jpeg', 'png'])],
        ];
    }
}
