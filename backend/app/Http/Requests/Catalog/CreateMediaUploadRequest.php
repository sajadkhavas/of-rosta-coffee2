<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateMediaUploadRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'filename',
            'mime_type',
            'size_bytes',
            'checksum_sha256',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255', 'not_regex:/[\\\/]/'],
            'mime_type' => [
                'required',
                'string',
                Rule::in(['image/jpeg', 'image/png', 'image/webp', 'image/avif']),
            ],
            'size_bytes' => [
                'required',
                'integer',
                'min:1',
                'max:'.max(1, (int) config('rosta.media_uploads.max_size_bytes', 12_000_000)),
            ],
            'checksum_sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
        ];
    }
}
