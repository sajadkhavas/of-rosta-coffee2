from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:180]!r}")
    file.write_text(source.replace(old, new))


# Feature requests represent the first-party TanStack SPA. Supplying the
# approved Origin and Referer lets Sanctum's stateful middleware install the
# session store exactly as it does for the production browser.
replace(
    "backend/tests/TestCase.php",
    '''abstract class TestCase extends BaseTestCase
{
    // Laravel 11 resolves bootstrap/app.php automatically.
}
''',
    '''abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ]);
    }
}
''',
)

# Laravel eager-load aggregate and relation callbacks receive Relation
# instances, not an Eloquent Builder. Removing the incorrect concrete type
# preserves fluent relation constraints and prevents runtime TypeError.
replace(
    "backend/app/Services/Catalog/PublicCatalogService.php",
    '''                'variants as min_active_price' => static fn (Builder $variants): Builder =>
                    $variants->where('is_active', true),
''',
    '''                'variants as min_active_price' => static fn ($variants) =>
                    $variants->where('is_active', true),
''',
)
replace(
    "backend/app/Services/Checkout/QuoteService.php",
    '''                'product.variants' => static fn (Builder $query): Builder =>
                    $query->where('is_active', true)->orderBy('weight_grams'),
''',
    '''                'product.variants' => static fn ($query) =>
                    $query->where('is_active', true)->orderBy('weight_grams'),
''',
)
