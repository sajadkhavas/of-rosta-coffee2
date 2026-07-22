<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertSeoRedirectRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'source_path',
            'destination_path',
            'status_code',
            'is_active',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $redirectId = $this->route('redirectId');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'source_path' => [
                $required,
                'string',
                'min:1',
                'max:500',
                Rule::unique('seo_redirects', 'source_path')->ignore($redirectId),
            ],
            'destination_path' => [$required, 'string', 'min:1', 'max:500', 'different:source_path'],
            'status_code' => ['sometimes', Rule::in([301, 308])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
