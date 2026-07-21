<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProcessingMethod;
use App\Enums\ProductStatus;
use App\Enums\RoastLevel;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertProductRequest extends FormRequest
{
    use RejectsUnexpectedInput;
    protected function prepareForValidation(): void { $this->rejectUnexpected(['origin_id','primary_media_id','name','slug','short_description','description','processing_method','roast_level','arabica_percentage','tasting_notes','brewing_suggestions','seo_title','seo_description','status','gallery_media_ids']); }
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $id = $this->route('productId'); $required = $this->isMethod('post') ? 'required' : 'sometimes';
        return ['origin_id' => [$required,'string','exists:origins,id'],'primary_media_id' => ['nullable','string','max:200'],'name' => [$required,'string','min:1','max:240'],'slug' => [$required,'string','min:1','max:180','regex:/^[A-Za-z0-9\x{0600}-\x{06FF}](?:[A-Za-z0-9\x{0600}-\x{06FF}_-]{0,178}[A-Za-z0-9\x{0600}-\x{06FF}])?$/u', Rule::unique('products','slug')->ignore($id)],'short_description' => ['nullable','string','max:1000'],'description' => ['sometimes','string','max:50000'],'processing_method' => [$required, Rule::enum(ProcessingMethod::class)],'roast_level' => [$required, Rule::enum(RoastLevel::class)],'arabica_percentage' => [$required,'integer','min:0','max:100'],'tasting_notes' => [$required,'array','max:30'],'tasting_notes.*' => ['string','min:1','max:100'],'brewing_suggestions' => ['sometimes','array','max:30'],'brewing_suggestions.*' => ['string','min:1','max:500'],'seo_title' => ['nullable','string','max:180'],'seo_description' => ['nullable','string','max:500'],'status' => ['sometimes', Rule::in([ProductStatus::Draft->value,ProductStatus::Review->value,ProductStatus::Archived->value])],'gallery_media_ids' => ['sometimes','array','max:30'],'gallery_media_ids.*' => ['string','max:200']];
    }
}
