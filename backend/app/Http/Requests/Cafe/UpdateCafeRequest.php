<?php

namespace App\Http\Requests\Cafe;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCafeRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['name', 'city', 'address', 'latitude', 'longitude', 'phone', 'website_url', 'instagram_handle', 'description', 'opening_hours', 'amenities']);
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'address' => ['sometimes', 'required', 'string', 'max:1000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'website_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'instagram_handle' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'opening_hours' => ['sometimes', 'nullable', 'array', 'max:7'],
            'amenities' => ['sometimes', 'nullable', 'array', 'max:50'],
            'amenities.*' => ['string', 'max:80', 'distinct:strict'],
        ];
    }
}
