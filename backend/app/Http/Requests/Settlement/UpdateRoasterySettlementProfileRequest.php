<?php

namespace App\Http\Requests\Settlement;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoasterySettlementProfileRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'entity_type',
            'legal_name',
            'account_holder_name',
            'iban',
        ]);

        if ($this->has('iban')) {
            $this->merge([
                'iban' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('iban')) ?? ''),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::in(['individual', 'company'])],
            'legal_name' => ['required', 'string', 'min:2', 'max:190'],
            'account_holder_name' => ['required', 'string', 'min:2', 'max:190'],
            'iban' => ['required', 'string', 'regex:/^IR\d{24}$/'],
        ];
    }
}
