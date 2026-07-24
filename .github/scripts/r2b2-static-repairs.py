from pathlib import Path

ROOT = Path("backend")


def replace(path: str, old: str, new: str) -> None:
    file = ROOT / path
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:180]!r}")
    file.write_text(source.replace(old, new))


# Correct generated model metadata with database-nullability proven by the
# committed migrations. This keeps defensive runtime checks meaningful.
nullable_properties = {
    "preparation_min_hours": "int",
    "preparation_max_hours": "int",
    "rating_value": "float",
    "maximum_discount": "int",
    "max_redemptions": "int",
    "free_over": "int",
    "compare_at_price": "int",
    "approved_at": "\\Carbon\\CarbonImmutable",
    "succeeded_at": "\\Carbon\\CarbonImmutable",
    "starts_at": "\\Carbon\\CarbonImmutable",
    "ends_at": "\\Carbon\\CarbonImmutable",
    "last_seen_at": "\\Carbon\\CarbonImmutable",
    "placed_at": "\\Carbon\\CarbonImmutable",
    "available_from": "\\Carbon\\CarbonImmutable",
    "last_hit_at": "\\Carbon\\CarbonImmutable",
}
for model in sorted((ROOT / "app/Models").glob("*.php")):
    source = model.read_text()
    for field, value_type in nullable_properties.items():
        source = source.replace(
            f" * @property {value_type} ${field}\n",
            f" * @property {value_type}|null ${field}\n",
        )
    model.write_text(source)

# Redis LLEN is already typed as int by the configured client.
replace(
    "app/Console/Commands/StagingAcceptance.php",
    "is_int($size) && $size >= 0,",
    "$size >= 0,",
)

# These database columns are non-null by schema. Use direct access instead of
# defensive nullsafe calls whose null branch cannot occur.
replace(
    "app/Http/Controllers/Admin/AdminFinanceController.php",
    "$case->opened_at?->toIso8601String()",
    "$case->opened_at->toIso8601String()",
)
replace(
    "app/Http/Controllers/Admin/AdminOperationsController.php",
    "$log->created_at?->toIso8601String()",
    "$log->created_at->toIso8601String()",
)
replace(
    "app/Http/Controllers/Admin/AdminOperationsController.php",
    "$item->available_at?->toIso8601String()",
    "$item->available_at->toIso8601String()",
)
replace(
    "app/Http/Resources/RoastBatchResource.php",
    "$this->roasted_at?->toIso8601String()",
    "$this->roasted_at->toIso8601String()",
)

# A local scope should call the concrete scope method instead of relying on a
# dynamic Builder method that cannot be proven inside the same model class.
replace(
    "app/Models/ContentEntry.php",
    "return $query->published()->where('robots_index', true);",
    "return $this->scopePublished($query)->where('robots_index', true);",
)

# The SEO audit must recognize both Laravel's dynamic scope syntax and the
# equivalent concrete scope call used for static-analysis certainty.
replace(
    "scripts/audit-seo-content-contract.php",
    '''$require(
    'app/Models/ContentEntry.php',
    '->published()',
    'Indexability must remain dependent on publication status.',
);
''',
    '''$contentEntryModel = $read('app/Models/ContentEntry.php');
if (
    ! str_contains($contentEntryModel, '->published()')
    && ! str_contains($contentEntryModel, 'scopePublished($query)')
) {
    $failures[] = 'Indexability must remain dependent on publication status.';
}
''',
)

# FilesystemAdapter::temporaryUploadUrl() is already typed as an array. Retain
# validation of the required URL field without a redundant array check.
replace(
    "app/Services/Catalog/MediaUploadService.php",
    "if (! is_array($signed) || ! isset($signed['url']) || ! is_string($signed['url'])) {",
    "if (! isset($signed['url']) || ! is_string($signed['url'])) {",
)

# Preserve Product as the Eloquent generic throughout catalog queries.
replace(
    "app/Services/Catalog/PublicCatalogService.php",
    "    private function publicProductQuery(): Builder\n",
    "    /** @return Builder<Product> */\n    private function publicProductQuery(): Builder\n",
)

# The public validator already rejects unsupported block types before this
# method, but the private method remains fail-closed if called independently.
replace(
    "app/Services/Content/ContentBlockValidator.php",
    "            'comparison_table' => $this->comparisonTable($block, $index),\n        };",
    "            'comparison_table' => $this->comparisonTable($block, $index),\n            default => throw $this->invalid($index, 'نوع بلوک پشتیبانی نمی‌شود.'),\n        };",
)

# Express nullable payment/refund identifiers explicitly for reconciliation
# deduplication rather than relying on nullsafe/coalesce narrowing.
replace(
    "app/Services/Finance/FinancialReconciliationService.php",
    "            $payment?->id ?? '-',\n            $refund?->id ?? '-',",
    "            $payment instanceof PaymentAttempt ? $payment->id : '-',\n            $refund instanceof RefundAttempt ? $refund->id : '-',",
)

# The testing provider always creates a deterministic local message ID. Its
# concrete implementation may narrow the interface's nullable return type.
replace(
    "app/Services/Notifications/Providers/TestingSmsProvider.php",
    "    ): ?string {",
    "    ): string {",
)

# Payment attempts always have an expiry by schema and creation contract.
replace(
    "app/Services/Payments/PaymentService.php",
    "            && (! $attempt->expires_at || $attempt->expires_at->isFuture())",
    "            && $attempt->expires_at->isFuture()",
)

# Normalization always prefixes a slash, including the empty-segment case.
replace(
    "app/Support/SeoPath.php",
    "        return $normalized === '' ? '/' : $normalized;",
    "        return $normalized;",
)
