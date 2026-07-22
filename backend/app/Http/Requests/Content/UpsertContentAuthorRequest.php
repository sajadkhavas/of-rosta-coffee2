<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertContentAuthorRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['name', 'slug', 'bio', 'credentials', 'is_active']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $authorId = $this->route('authorId');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:1', 'max:160'],
            'slug' => [
                $required,
                'string',
                'min:1',
                'max:180',
                'regex:/^[A-Za-z0-9\x{0600}-\x{06FF}](?:[A-Za-z0-9\x{0600}-\x{06FF}_-]{0,178}[A-Za-z0-9\x{0600}-\x{06FF}])?$/u',
                Rule::unique('content_authors', 'slug')->ignore($authorId),
            ],
            'bio' => ['nullable', 'string', 'max:10000'],
            'credentials' => ['sometimes', 'array', 'max:20'],
            'credentials.*' => ['string', 'min:1', 'max:300'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
