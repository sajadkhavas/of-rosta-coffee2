<?php

namespace App\Http\Requests\Content;

use App\Enums\ContentType;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertContentEntryRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'author_id',
            'type',
            'title',
            'slug',
            'canonical_path',
            'excerpt',
            'body',
            'seo_title',
            'seo_description',
            'robots_index',
            'robots_follow',
            'og_title',
            'og_description',
            'og_media_url',
            'schema_type',
            'keywords',
            'relations',
        ]);

        $relations = $this->input('relations');
        if (is_array($relations)) {
            $this->rejectUnexpectedNested(
                $relations,
                [
                    'relation_type',
                    'target_type',
                    'target_key',
                    'anchor_text',
                    'position',
                ],
                'relations',
            );
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $entryId = $this->route('entryId');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $slugPattern = 'regex:/^[A-Za-z0-9\x{0600}-\x{06FF}](?:[A-Za-z0-9\x{0600}-\x{06FF}_-]{0,238}[A-Za-z0-9\x{0600}-\x{06FF}])?$/u';

        return [
            'author_id' => ['nullable', 'string', 'exists:content_authors,id'],
            'type' => [$required, Rule::enum(ContentType::class)],
            'title' => [$required, 'string', 'min:1', 'max:240'],
            'slug' => [
                $required,
                'string',
                'min:1',
                'max:180',
                'regex:/^[A-Za-z0-9\x{0600}-\x{06FF}](?:[A-Za-z0-9\x{0600}-\x{06FF}_-]{0,178}[A-Za-z0-9\x{0600}-\x{06FF}])?$/u',
                Rule::unique('content_entries', 'slug')->ignore($entryId),
            ],
            'canonical_path' => [
                $required,
                'string',
                'min:1',
                'max:500',
                Rule::unique('content_entries', 'canonical_path')->ignore($entryId),
            ],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => [$required, 'array', 'min:1', 'max:200'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'robots_index' => ['sometimes', 'boolean'],
            'robots_follow' => ['sometimes', 'boolean'],
            'og_title' => ['nullable', 'string', 'max:180'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_media_url' => ['nullable', 'url:https', 'max:2000'],
            'schema_type' => [
                'sometimes',
                Rule::in([
                    'Article',
                    'BlogPosting',
                    'CollectionPage',
                    'FAQPage',
                    'WebPage',
                ]),
            ],
            'keywords' => ['sometimes', 'array', 'max:30'],
            'keywords.*' => ['string', 'min:1', 'max:120'],
            'relations' => ['sometimes', 'array', 'max:100'],
            'relations.*.relation_type' => [
                'required',
                Rule::in([
                    'related',
                    'mentions',
                    'recommends',
                    'compares',
                    'primary_topic',
                ]),
            ],
            'relations.*.target_type' => [
                'required',
                Rule::in([
                    'content',
                    'product',
                    'roastery',
                    'origin',
                    'brew_method',
                    'taste',
                ]),
            ],
            'relations.*.target_key' => [
                'required',
                'string',
                'min:1',
                'max:240',
                $slugPattern,
            ],
            'relations.*.anchor_text' => ['nullable', 'string', 'max:300'],
            'relations.*.position' => [
                'sometimes',
                'integer',
                'min:0',
                'max:1000',
            ],
        ];
    }
}
