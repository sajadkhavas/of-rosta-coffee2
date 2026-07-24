from pathlib import Path
import re

ROOT = Path("backend")

RESOURCE_MODELS = {
    "AuthUserResource.php": "User",
    "CheckoutQuoteResource.php": "CheckoutQuote",
    "ContentAuthorResource.php": "ContentAuthor",
    "ContentEntrySummaryResource.php": "ContentEntry",
    "ContentEntryDetailResource.php": "ContentEntry",
    "ContentRelationResource.php": "ContentRelation",
    "MediaAssetResource.php": "MediaAsset",
    "OrderResource.php": "Order",
    "OriginResource.php": "Origin",
    "ProductSummaryResource.php": "Product",
    "ProductDetailResource.php": "Product",
    "ProductVariantResource.php": "ProductVariant",
    "RoastBatchResource.php": "RoastBatch",
    "RoasterySummaryResource.php": "Roastery",
    "RoasteryDetailResource.php": "Roastery",
    "SeoRedirectResource.php": "SeoRedirect",
    "StockLedgerEntryResource.php": "StockLedgerEntry",
}

NON_NULL_DATES = {
    "expires_at",
    "resend_available_at",
    "available_at",
    "roasted_at",
    "available_from",
    "restocked_at",
    "opened_at",
    "placed_at",
    "created_at",
    "updated_at",
    "last_seen_at",
}
NULLABLE_DATE_PARTS = (
    "revoked",
    "consumed",
    "locked",
    "published",
    "paid",
    "refunded",
    "cancelled",
    "failed",
    "verified",
    "review_required",
    "processing",
    "sent",
    "completed",
    "released",
    "resolved",
    "accepted",
    "rejected",
    "preparing",
    "ready_to_ship",
    "shipped",
    "delivered",
    "moderated",
)


def relation_methods(source: str):
    pattern = re.compile(
        r"(?m)^    public function (\w+)\(\):\s*"
        r"(BelongsTo|HasMany|HasOne|BelongsToMany|MorphTo|MorphMany|MorphOne)\s*\{"
    )
    methods = []
    for match in pattern.finditer(source):
        brace = source.find("{", match.start())
        depth = 0
        quote = None
        escaped = False
        end = None
        for index in range(brace, len(source)):
            character = source[index]
            if quote is not None:
                if escaped:
                    escaped = False
                elif character == "\\":
                    escaped = True
                elif character == quote:
                    quote = None
                continue
            if character in ("'", '"'):
                quote = character
                continue
            if character == "{":
                depth += 1
            elif character == "}":
                depth -= 1
                if depth == 0:
                    end = index + 1
                    break
        if end is None:
            raise RuntimeError(f"Unbalanced relation method: {match.group(1)}")
        methods.append((match.group(1), match.group(2), source[brace + 1 : end - 1]))
    return methods


def target_from_body(body: str, relation_type: str, imports: dict[str, str]) -> str:
    if relation_type == "MorphTo":
        return "\\Illuminate\\Database\\Eloquent\\Model"
    target = re.search(
        r"\$this->(?:belongsTo|hasMany|hasOne|belongsToMany|morphMany|morphOne)"
        r"\(\s*([A-Za-z_\\][A-Za-z0-9_\\]*)::class",
        body,
    )
    if target is None:
        return "\\Illuminate\\Database\\Eloquent\\Model"
    token = target.group(1)
    if token.startswith("\\"):
        return token
    if "\\" in token:
        return "\\" + token
    return "\\" + imports.get(token, "App\\Models\\" + token)


def cast_type(field: str, expression: str, imports: dict[str, str]) -> str:
    expression = expression.strip()
    if expression.endswith("::class"):
        token = expression[:-7]
        return "\\" + imports.get(token, "App\\Enums\\" + token)
    if "'integer'" in expression:
        return "int"
    if "'float'" in expression:
        return "float"
    if "'boolean'" in expression:
        return "bool"
    if "'immutable_datetime'" in expression:
        value = "\\Carbon\\CarbonImmutable"
        if field not in NON_NULL_DATES and any(part in field for part in NULLABLE_DATE_PARTS):
            value += "|null"
        return value
    if "'array'" in expression or "'encrypted:array'" in expression:
        return "array<mixed>"
    if "'encrypted'" in expression:
        return "string|null"
    return "mixed"


for filename, model in RESOURCE_MODELS.items():
    path = ROOT / "app/Http/Resources" / filename
    source = path.read_text()
    if "@mixin" in source:
        raise RuntimeError(f"Resource already has a mixin: {filename}")
    pattern = re.compile(r"(?m)^(final\s+class|class)\s+")
    match = pattern.search(source)
    if match is None:
        raise RuntimeError(f"Could not annotate resource: {filename}")
    annotation = f"/** @mixin \\App\\Models\\{model} */\n{match.group(1)} "
    path.write_text(source[: match.start()] + annotation + source[match.end() :])


for path in sorted((ROOT / "app/Models").glob("*.php")):
    source = path.read_text()
    class_match = re.search(r"(?m)^(final\s+class|class)\s+(\w+)", source)
    if class_match is None:
        continue
    if "@property" in source[max(0, class_match.start() - 500) : class_match.start()]:
        raise RuntimeError(f"Model already contains property metadata: {path.name}")

    imports = {
        imported.split("\\")[-1]: imported
        for imported in re.findall(r"^use\s+([^;]+);", source, re.MULTILINE)
    }
    fillable_match = re.search(r"protected \$fillable = \[(.*?)\];", source, re.DOTALL)
    fillable = re.findall(r"'([^']+)'", fillable_match.group(1)) if fillable_match else []

    casts: dict[str, str] = {}
    casts_match = re.search(
        r"protected function casts\(\): array\s*\{\s*return \[(.*?)\];",
        source,
        re.DOTALL,
    )
    if casts_match:
        for field, expression in re.findall(
            r"'([^']+)'\s*=>\s*([^,\n]+)", casts_match.group(1)
        ):
            casts[field] = cast_type(field, expression, imports)

    properties: list[tuple[str, str]] = [("id", "string")]
    for field in fillable:
        if field in casts:
            value_type = casts[field]
        elif field.endswith("_id") or field in {
            "reviewed_by",
            "assigned_to",
            "requested_by",
            "approved_by",
            "resolved_by",
            "created_by",
        }:
            value_type = "string|null"
        else:
            value_type = "string|null"
        properties.append((field, value_type))
    existing = {field for field, _ in properties}
    properties.extend((field, value_type) for field, value_type in casts.items() if field not in existing)
    properties.extend(
        [
            ("created_at", "\\Carbon\\CarbonImmutable|null"),
            ("updated_at", "\\Carbon\\CarbonImmutable|null"),
        ]
    )

    relations = []
    for name, relation_type, body in relation_methods(source):
        target = target_from_body(body, relation_type, imports)
        if relation_type in {"HasMany", "BelongsToMany", "MorphMany"}:
            property_type = "\\Illuminate\\Database\\Eloquent\\Collection<int, " + target + ">"
        else:
            property_type = target + "|null"
        relations.append((name, relation_type, target, property_type))

    scopes = re.findall(r"public function scope([A-Z]\w*)\(", source)
    metadata = []
    seen = set()
    for field, value_type in properties:
        if field in seen:
            continue
        seen.add(field)
        metadata.append(f" * @property {value_type} ${field}")
    for name, _, _, property_type in relations:
        metadata.append(f" * @property-read {property_type} ${name}")
    for scope in scopes:
        method = scope[0].lower() + scope[1:]
        metadata.append(
            " * @method static \\Illuminate\\Database\\Eloquent\\Builder<static> "
            + method
            + "()"
        )

    docblock = "/**\n" + "\n".join(metadata) + "\n */\n"
    source = source[: class_match.start()] + docblock + source[class_match.start() :]

    for name, relation_type, target, _ in relations:
        if relation_type == "MorphTo":
            generic = "MorphTo<\\Illuminate\\Database\\Eloquent\\Model, $this>"
        else:
            generic = f"{relation_type}<{target}, $this>"
        signature = f"    public function {name}(): {relation_type}"
        replacement = f"    /** @return {generic} */\n{signature}"
        if signature not in source:
            raise RuntimeError(f"Missing relation signature {path.name}:{name}")
        source = source.replace(signature, replacement, 1)

    path.write_text(source)
