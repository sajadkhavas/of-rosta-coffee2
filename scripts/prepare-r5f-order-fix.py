from pathlib import Path

path = Path(__file__).with_name("apply-r5f.py")
text = path.read_text(encoding="utf-8")
marker = 'order_route = "src/routes/orders.$id.tsx"\n'
start = text.index("replace(\n", text.index(marker) + len(marker))
end = text.index("\nreplace(\n", start)
replacement = '''order_path = ROOT / order_route
order_text = order_path.read_text(encoding="utf-8")
order_pattern = __import__("re").compile(
    r'<p className="mt-2 font-mono text-sm font-bold text-\\[color:var\\(--roast\\)\\]">\\s*'
    r'\\{formatIrr\\(item\\.lineTotal\\)\\}\\s*</p>'
)
order_matches = list(order_pattern.finditer(order_text))
if len(order_matches) != 1:
    raise SystemExit(f"{order_route}: expected one order line price block, found {len(order_matches)}")
order_service_markup = order_matches[0].group(0) + """
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
order_path.write_text(order_pattern.sub(lambda _: order_service_markup, order_text, count=1), encoding="utf-8")'''
text = text[:start] + replacement + text[end:]
path.write_text(text, encoding="utf-8")
print("R5F order UI anchor repaired")
