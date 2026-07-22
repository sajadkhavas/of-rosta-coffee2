<?php

namespace App\Http\Requests\Reviews;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class CreateReviewRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['order_item_id', 'rating', 'title', 'body']);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:10', 'max:4000'],
        ];
    }
}
