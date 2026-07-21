<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertOriginRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['name', 'slug', 'country_code']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $originId = $this->route('originId');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:1', 'max:160'],
            'slug' => [
                $required,
                'string',
                'min:1',
                'max:180',
                'regex:/^[A-Za-z0-9\x{0600}-\x{06FF}](?:[A-Za-z0-9\x{0600}-\x{06FF}_-]{0,178}[A-Za-z0-9\x{0600}-\x{06FF}])?$/u',
                Rule::unique('origins', 'slug')->ignore($originId),
            ],
            'country_code' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ];
    }
}
