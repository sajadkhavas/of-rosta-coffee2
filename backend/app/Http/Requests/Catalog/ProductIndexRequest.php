<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProcessingMethod;
use App\Enums\RoastLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        foreach (['origin','roast_level','processing_method','roastery','weight'] as $key) {
            $value = $this->query($key);
            if (is_string($value)) $this->merge([$key => array_values(array_filter(explode(',', $value)))]);
        }
    }
    public function rules(): array
    {
        return ['page' => ['sometimes','integer','min:1','max:10000'],'per_page' => ['sometimes','integer','min:1','max:100'],'q' => ['sometimes','string','max:120'],'origin' => ['sometimes','array','max:20'],'origin.*' => ['string','max:200'],'roast_level' => ['sometimes','array','max:3'],'roast_level.*' => [Rule::enum(RoastLevel::class)],'processing_method' => ['sometimes','array','max:4'],'processing_method.*' => [Rule::enum(ProcessingMethod::class)],'roastery' => ['sometimes','array','max:20'],'roastery.*' => ['string','max:200'],'weight' => ['sometimes','array','max:5'],'weight.*' => ['integer', Rule::in(config('rosta.whole_bean_weights'))],'min_price' => ['sometimes','integer','min:0'],'max_price' => ['sometimes','integer','min:0','gte:min_price'],'available' => ['sometimes','boolean'],'sort' => ['sometimes', Rule::in(['recommended','newest','price_asc','price_desc'])]];
    }
}
