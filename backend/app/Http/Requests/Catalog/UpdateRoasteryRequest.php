<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoasteryRequest extends FormRequest
{
    use RejectsUnexpectedInput;
    protected function prepareForValidation(): void { $this->rejectUnexpected(['name','slug','city','description','shipping_policy','preparation_min_hours','preparation_max_hours','logo_media_id','cover_media_id']); }
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $id = (string) $this->route('roasteryId');
        return ['name' => ['sometimes','string','min:1','max:160'],'slug' => ['sometimes','string','min:1','max:180','regex:/^[A-Za-z0-9\x{0600}-\x{06FF}](?:[A-Za-z0-9\x{0600}-\x{06FF}_-]{0,178}[A-Za-z0-9\x{0600}-\x{06FF}])?$/u', Rule::unique('roasteries','slug')->ignore($id)],'city' => ['nullable','string','max:120'],'description' => ['sometimes','string','max:20000'],'shipping_policy' => ['nullable','string','max:10000'],'preparation_min_hours' => ['nullable','integer','min:0','max:720'],'preparation_max_hours' => ['nullable','integer','min:0','max:720','gte:preparation_min_hours'],'logo_media_id' => ['nullable','string','max:200'],'cover_media_id' => ['nullable','string','max:200']];
    }
}
