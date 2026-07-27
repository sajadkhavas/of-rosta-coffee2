from pathlib import Path

path = Path(__file__).with_name("apply-r5f.py")
text = path.read_text(encoding="utf-8")
marker = 'order_route = "src/routes/orders.$id.tsx"\n'
start = text.index(marker) + len(marker)
end = text.index("\n# Permanent gates.", start)
replacement = '''order_path = ROOT / order_route
order_text = order_path.read_text(encoding="utf-8")
regex = __import__("re")

price_pattern = regex.compile(
    r'<p className="mt-2 font-mono text-sm font-bold text-\\[color:var\\(--roast\\)\\]">\\s*'
    r'\\{formatIrr\\(item\\.lineTotal\\)\\}\\s*</p>'
)
price_matches = list(price_pattern.finditer(order_text))
if len(price_matches) != 1:
    raise SystemExit(f"{order_route}: expected one order line price block, found {len(price_matches)}")
price_markup = price_matches[0].group(0) + """
{item.services.length ? (
  <ul className="mt-3 space-y-1 text-[11px] text-[color:var(--light)]">
    {item.services.map((service) => (
      <li key={service.id} className="flex flex-wrap justify-between gap-2">
        <span>
          {service.type === "grinding"
            ? service.grindingProfile?.name ?? service.label ?? "آسیاب روستری"
            : service.label ?? "بسته‌بندی روستری"}
        </span>
        <span>{service.isFree ? "رایگان" : formatIrr(service.totalAmount)}</span>
      </li>
    ))}
  </ul>
) : null}
"""
order_text = price_pattern.sub(lambda _: price_markup, order_text, count=1)

suborder_shipping_pattern = regex.compile(
    r'<div className="flex justify-between text-\\[color:var\\(--light\\)\\]">\\s*'
    r'<dt>ارسال</dt>\\s*<dd>\\{formatIrr\\(subOrder\\.shippingTotal\\)\\}</dd>\\s*</div>'
)
suborder_matches = list(suborder_shipping_pattern.finditer(order_text))
if len(suborder_matches) != 1:
    raise SystemExit(
        f"{order_route}: expected one sub-order shipping summary, found {len(suborder_matches)}"
    )
suborder_markup = """
<div className="flex justify-between text-[color:var(--light)]">
  <dt>بسته‌بندی</dt>
  <dd>{subOrder.packagingTotal === 0 ? "رایگان" : formatIrr(subOrder.packagingTotal)}</dd>
</div>
<div className="flex justify-between text-[color:var(--light)]">
  <dt>آسیاب</dt>
  <dd>{subOrder.grindingTotal === 0 ? "—" : formatIrr(subOrder.grindingTotal)}</dd>
</div>
<div className="flex justify-between text-[color:var(--light)]">
  <dt>ارسال</dt>
  <dd>{formatIrr(subOrder.shippingTotal)}</dd>
</div>
"""
order_text = suborder_shipping_pattern.sub(lambda _: suborder_markup, order_text, count=1)

order_shipping_pattern = regex.compile(
    r'<div className="flex justify-between text-\\[color:var\\(--light\\)\\]">\\s*'
    r'<dt>ارسال</dt>\\s*<dd>\\{formatIrr\\(order\\.shippingTotal\\)\\}</dd>\\s*</div>'
)
order_matches = list(order_shipping_pattern.finditer(order_text))
if len(order_matches) != 1:
    raise SystemExit(f"{order_route}: expected one order shipping summary, found {len(order_matches)}")
order_markup = """
<div className="flex justify-between text-[color:var(--light)]">
  <dt>بسته‌بندی</dt>
  <dd>{order.packagingTotal === 0 ? "رایگان" : formatIrr(order.packagingTotal)}</dd>
</div>
<div className="flex justify-between text-[color:var(--light)]">
  <dt>آسیاب</dt>
  <dd>{order.grindingTotal === 0 ? "—" : formatIrr(order.grindingTotal)}</dd>
</div>
<div className="flex justify-between text-[color:var(--light)]">
  <dt>ارسال</dt>
  <dd>{formatIrr(order.shippingTotal)}</dd>
</div>
"""
order_text = order_shipping_pattern.sub(lambda _: order_markup, order_text, count=1)
order_path.write_text(order_text, encoding="utf-8")
'''
text = text[:start] + replacement + text[end:]
path.write_text(text, encoding="utf-8")
print("R5F order UI bounded blocks repaired")
