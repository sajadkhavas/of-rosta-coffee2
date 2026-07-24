from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:180]!r}")
    file.write_text(source.replace(old, new))


# A small explicit base resource prevents Laravel's wasRecentlyCreated flag
# from silently changing stable read/action endpoints from 200 to 201.
ok_resource = Path("backend/app/Http/Resources/OkJsonResource.php")
if ok_resource.exists():
    raise RuntimeError("OkJsonResource.php unexpectedly already exists")
ok_resource.write_text(
    """<?php

namespace App\\Http\\Resources;

use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

abstract class OkJsonResource extends JsonResource
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }
}
"""
)

for resource in (
    "AuthUserResource.php",
    "CheckoutQuoteResource.php",
    "ContentAuthorResource.php",
    "ProductVariantResource.php",
    "SeoRedirectResource.php",
    "StockLedgerEntryResource.php",
):
    path = f"backend/app/Http/Resources/{resource}"
    replace(path, "use Illuminate\\Http\\Resources\\Json\\JsonResource;\n", "")
    replace(path, "extends JsonResource", "extends OkJsonResource")

# The remaining eager-load callback also receives a Relation instance.
replace(
    "backend/app/Services/Catalog/PublicCatalogService.php",
    '''                'variants' => static fn (Builder $variants): Builder =>
                    $variants->where('is_active', true)->orderBy('weight_grams'),
''',
    '''                'variants' => static fn ($variants) =>
                    $variants->where('is_active', true)->orderBy('weight_grams'),
''',
)

# Avoid an invalid regex character range while preserving both control-byte
# and backslash rejection.
replace(
    "backend/app/Support/SeoPath.php",
    """        if (preg_match('/[\\\\\\x00-\\x1F\\x7F]/u', $candidate) === 1) {
""",
    """        if (
            str_contains($candidate, '\\\\')
            || preg_match('/[\\x00-\\x1F\\x7F]/', $candidate) === 1
        ) {
""",
)

# Switching authenticated identities in one feature test must discard the
# previous Sanctum session state before installing the new recorded session.
replace(
    "backend/tests/Support/AuthenticatesRecordedSession.php",
    '''        $this->actingAs($user, 'web')->withSession([
            AuthSessionService::SESSION_KEY => $session->id,
        ]);
''',
    '''        $this->flushSession();
        $this->actingAs($user, 'web')->withSession([
            AuthSessionService::SESSION_KEY => $session->id,
        ]);
''',
)

# Validation errors use Rosta's frozen API envelope: error.fields.
replace(
    "backend/tests/Feature/FulfillmentLifecycleTest.php",
    '''        $this->patchJson($endpoint, ['status' => 'shipped'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['carrier', 'tracking_code']);
''',
    '''        $this->patchJson($endpoint, ['status' => 'shipped'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'request.validation_failed')
            ->assertJsonStructure([
                'error' => ['fields' => ['carrier', 'tracking_code']],
            ]);
''',
)
replace(
    "backend/tests/Feature/InquiryWorkflowTest.php",
    '''        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['website']);
''',
    '''        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'request.validation_failed')
            ->assertJsonStructure([
                'error' => ['fields' => ['website']],
            ]);
''',
)
