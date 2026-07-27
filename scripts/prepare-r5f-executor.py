from pathlib import Path

path = Path(__file__).with_name("apply-r5f.py")
text = path.read_text(encoding="utf-8")


def swap(old: str, new: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"prepare-r5f: expected one block, found {count}: {old[:100]!r}")
    text = text.replace(old, new)


swap(
    '''replace(quote, "                    'grinding_total' => 0,", "                    'grinding_total' => $group['grinding_total'],")''',
    '''replace(
    quote,
    "                    'packaging_total' => $group['packaging_total'],\\n"
    "                    'grinding_total' => 0,",
    "                    'packaging_total' => $group['packaging_total'],\\n"
    "                    'grinding_total' => $group['grinding_total'],",
)''',
)

swap(
    '''replace(quote, "                        'version' => 'r5d-product-packaging-v1',", "                        'version' => 'r5f-roastery-grinding-v1',", expected=1)''',
    '''replace(
    quote,
    "                    'pricing_snapshot' => [\\n"
    "                        'version' => 'r5d-product-packaging-v1',",
    "                    'pricing_snapshot' => [\\n"
    "                        'version' => 'r5f-roastery-grinding-v1',",
)''',
)

swap(
    '''replace(
    cart_context,
    "      updateQuantity,\\n      clear,",
    "      updateQuantity,\\n"
    "      setGrindingProfile,\\n"
    "      clear,",
)
replace(
    cart_context,
    "      updateQuantity,\\n      clear,\\n      itemCount,",
    "      updateQuantity,\\n"
    "      setGrindingProfile,\\n"
    "      clear,\\n"
    "      itemCount,",
)''',
    '''replace(
    cart_context,
    "      updateQuantity,\\n      clear,",
    "      updateQuantity,\\n"
    "      setGrindingProfile,\\n"
    "      clear,",
    expected=2,
)''',
)

swap(
    '''replace(
    schemas,
    "    packaging_total: moneySchema,\\n    shipping_total:",
    "    packaging_total: moneySchema,\\n"
    "    grinding_total: moneySchema,\\n"
    "    shipping_total:",
    expected=1,
)''',
    '''replace(
    schemas,
    "    groups: z.array(quoteGroupWireSchema).min(1).max(50),\\n"
    "    subtotal: moneySchema,\\n"
    "    packaging_total: moneySchema,\\n"
    "    shipping_total: moneySchema,",
    "    groups: z.array(quoteGroupWireSchema).min(1).max(50),\\n"
    "    subtotal: moneySchema,\\n"
    "    packaging_total: moneySchema,\\n"
    "    grinding_total: moneySchema,\\n"
    "    shipping_total: moneySchema,",
)''',
)

swap(
    '''replace(
    schemas,
    "      packagingTotal !== value.packaging_total ||\\n      shippingTotal:",
    "      packagingTotal !== value.packaging_total ||\\n"
    "      grindingTotal !== value.grinding_total ||\\n"
    "      shippingTotal:",
)
# The previous replacement target contains an equality expression, not a field separator.
''',
    '''''',
)

checkout_marker = 'checkout_route = "src/routes/checkout.tsx"\n'
checkout_start = text.index("replace(\n", text.index(checkout_marker) + len(checkout_marker))
checkout_end = text.index("\nreplace(\n", checkout_start)
checkout_replacement = '''replace(
    checkout_route,
    "</p>\\n                </div>\\n                <span className=\\"shrink-0 font-mono text-[color:var(--light)]\\">",
    "</p>\\n"
    "                  {quote?.groups\\n"
    "                    .flatMap((group) => group.items)\\n"
    "                    .find((line) => line.variant.id === item.variantId)\\n"
    "                    ?.services.filter((service) => service.type === \\"grinding\\")\\n"
    "                    .map((service) => (\\n"
    "                      <p key={service.id} className=\\"mt-1 text-[10px] text-[color:var(--roast)]\\">\\n"
    "                        {service.label ?? service.grindingProfile?.name ?? \\"آسیاب روستری\\"} ·{\\" \\"}\\n"
    "                        {service.isFree ? \\"رایگان\\" : formatIrr(service.serviceFee)}\\n"
    "                      </p>\\n"
    "                    ))}\\n"
    "                </div>\\n"
    "                <span className=\\"shrink-0 font-mono text-[color:var(--light)]\\">",
)'''
text = text[:checkout_start] + checkout_replacement + text[checkout_end:]

path.write_text(text, encoding="utf-8")
print("R5F executor preparation complete")
