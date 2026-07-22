<?php

namespace App\Services\Content;

use App\Exceptions\ApiDomainException;

final class ContentBlockValidator
{
    private const BLOCK_KEYS = [
        'paragraph' => ['type', 'text'],
        'heading' => ['type', 'level', 'text'],
        'list' => ['type', 'style', 'items'],
        'quote' => ['type', 'text', 'citation'],
        'callout' => ['type', 'tone', 'text'],
        'faq' => ['type', 'items'],
        'product_grid' => ['type', 'product_slugs'],
        'roastery_spotlight' => ['type', 'roastery_slug'],
        'comparison_table' => ['type', 'columns', 'rows'],
    ];

    /**
     * @param mixed $body
     * @return list<array<string, mixed>>
     */
    public function validate(mixed $body): array
    {
        if (! is_array($body) || ! array_is_list($body) || $body === [] || count($body) > 200) {
            throw new ApiDomainException('content.body_invalid', 'محتوا باید بین ۱ تا ۲۰۰ بلوک معتبر داشته باشد.', 422);
        }

        $normalized = [];
        foreach ($body as $index => $block) {
            if (! is_array($block) || ! isset($block['type']) || ! is_string($block['type'])) {
                throw $this->invalid($index, 'نوع بلوک مشخص نیست.');
            }

            $type = $block['type'];
            $allowed = self::BLOCK_KEYS[$type] ?? null;
            if ($allowed === null) {
                throw $this->invalid($index, 'نوع بلوک پشتیبانی نمی‌شود.');
            }

            $unexpected = array_diff(array_keys($block), $allowed);
            if ($unexpected !== []) {
                throw $this->invalid($index, 'بلوک دارای فیلد تعریف‌نشده است.');
            }

            $normalized[] = $this->validateBlock($index, $type, $block);
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $body
     */
    public function hash(array $body): string
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return hash('sha256', $json);
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function validateBlock(int $index, string $type, array $block): array
    {
        return match ($type) {
            'paragraph' => ['type' => $type, 'text' => $this->text($block['text'] ?? null, 6000, $index)],
            'heading' => [
                'type' => $type,
                'level' => $this->enum($block['level'] ?? null, [2, 3], $index),
                'text' => $this->text($block['text'] ?? null, 240, $index),
            ],
            'list' => [
                'type' => $type,
                'style' => $this->enum($block['style'] ?? null, ['ordered', 'unordered'], $index),
                'items' => $this->textList($block['items'] ?? null, 50, 1000, $index),
            ],
            'quote' => [
                'type' => $type,
                'text' => $this->text($block['text'] ?? null, 3000, $index),
                'citation' => $this->nullableText($block['citation'] ?? null, 300, $index),
            ],
            'callout' => [
                'type' => $type,
                'tone' => $this->enum($block['tone'] ?? null, ['info', 'tip', 'warning'], $index),
                'text' => $this->text($block['text'] ?? null, 3000, $index),
            ],
            'faq' => ['type' => $type, 'items' => $this->faqItems($block['items'] ?? null, $index)],
            'product_grid' => [
                'type' => $type,
                'product_slugs' => $this->slugList($block['product_slugs'] ?? null, 24, $index),
            ],
            'roastery_spotlight' => [
                'type' => $type,
                'roastery_slug' => $this->slug($block['roastery_slug'] ?? null, $index),
            ],
            'comparison_table' => $this->comparisonTable($block, $index),
        };
    }

    private function text(mixed $value, int $max, int $index): string
    {
        if (! is_string($value)) {
            throw $this->invalid($index, 'متن بلوک معتبر نیست.');
        }
        $text = trim($value);
        if ($text === '' || mb_strlen($text) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1) {
            throw $this->invalid($index, 'طول یا نویسه‌های متن بلوک معتبر نیست.');
        }
        return $text;
    }

    private function nullableText(mixed $value, int $max, int $index): ?string
    {
        if ($value === null || $value === '') return null;
        return $this->text($value, $max, $index);
    }

    /** @param list<mixed> $allowed */
    private function enum(mixed $value, array $allowed, int $index): mixed
    {
        if (! in_array($value, $allowed, true)) throw $this->invalid($index, 'مقدار بلوک خارج از فهرست مجاز است.');
        return $value;
    }

    /** @return list<string> */
    private function textList(mixed $value, int $maxItems, int $maxLength, int $index): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === [] || count($value) > $maxItems) {
            throw $this->invalid($index, 'فهرست بلوک معتبر نیست.');
        }
        return array_map(fn (mixed $item): string => $this->text($item, $maxLength, $index), $value);
    }

    /** @return list<string> */
    private function slugList(mixed $value, int $maxItems, int $index): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === [] || count($value) > $maxItems) {
            throw $this->invalid($index, 'فهرست Slug معتبر نیست.');
        }
        return array_values(array_unique(array_map(fn (mixed $slug): string => $this->slug($slug, $index), $value)));
    }

    private function slug(mixed $value, int $index): string
    {
        $slug = $this->text($value, 180, $index);
        if (preg_match('/^[A-Za-z0-9\x{0600}-\x{06FF}](?:[A-Za-z0-9\x{0600}-\x{06FF}_-]{0,178}[A-Za-z0-9\x{0600}-\x{06FF}])?$/u', $slug) !== 1) {
            throw $this->invalid($index, 'Slug بلوک معتبر نیست.');
        }
        return $slug;
    }

    /** @return list<array{question: string, answer: string}> */
    private function faqItems(mixed $value, int $index): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === [] || count($value) > 30) {
            throw $this->invalid($index, 'FAQ معتبر نیست.');
        }
        $items = [];
        foreach ($value as $item) {
            if (! is_array($item) || array_diff(array_keys($item), ['question', 'answer']) !== []) {
                throw $this->invalid($index, 'ساختار FAQ معتبر نیست.');
            }
            $items[] = [
                'question' => $this->text($item['question'] ?? null, 300, $index),
                'answer' => $this->text($item['answer'] ?? null, 3000, $index),
            ];
        }
        return $items;
    }

    /** @param array<string, mixed> $block */
    private function comparisonTable(array $block, int $index): array
    {
        $columns = $this->textList($block['columns'] ?? null, 8, 120, $index);
        $rows = $block['rows'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || $rows === [] || count($rows) > 50) {
            throw $this->invalid($index, 'ردیف‌های جدول معتبر نیست.');
        }
        $normalizedRows = [];
        foreach ($rows as $row) {
            $cells = $this->textList($row, count($columns), 1000, $index);
            if (count($cells) !== count($columns)) throw $this->invalid($index, 'تعداد سلول‌های جدول با ستون‌ها برابر نیست.');
            $normalizedRows[] = $cells;
        }
        return ['type' => 'comparison_table', 'columns' => $columns, 'rows' => $normalizedRows];
    }

    private function invalid(int $index, string $message): ApiDomainException
    {
        return new ApiDomainException('content.block_invalid', 'بلوک شماره '.($index + 1).': '.$message, 422);
    }
}
