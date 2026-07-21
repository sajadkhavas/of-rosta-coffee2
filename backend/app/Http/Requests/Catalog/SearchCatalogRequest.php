<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchCatalogRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['q' => ['required','string','min:1','max:120'],'type' => ['sometimes', Rule::in(['all','products','roasteries','content'])]];
    }
}
