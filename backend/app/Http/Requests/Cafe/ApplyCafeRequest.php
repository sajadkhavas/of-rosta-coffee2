<?php

namespace App\Http\Requests\Cafe;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class ApplyCafeRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['name', 'slug', 'city', 'address', 'latitude', 'longitude', 'phone', 'website_url', 'instagram_handle', 'description', 'opening_hours', 'amenities']);
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'phone' => ['nullable', 'string', 'max:32'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram_handle' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:4000'],
            'opening_hours' => ['nullable', 'array', 'max:7'],
            'amenities' => ['nullable', 'array', 'max:50'],
            'amenities.*' => ['string', 'max:80', 'distinct:strict'],
        ];
    }
}
