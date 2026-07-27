from pathlib import Path

path = Path(__file__).with_name("apply-r5f.py")
text = path.read_text(encoding="utf-8")
marker = 'order_route = "src/routes/orders.$id.tsx"\n'
start = text.index("replace(\n", text.index(marker) + len(marker))
end = text.index("\nreplace(\n", start)
replacement = '''replace(
    order_route,
    "{formatIrr(item.lineTotal)}\\n                         </p>",
    "{formatIrr(item.lineTotal)}\\n"
    "                         </p>\\n"
    "                         {item.services.length ? (\\n"
    "                           <ul className=\\"mt-3 space-y-1 text-[11px] text-[color:var(--light)]\\">\\n"
    "                             {item.services.map((service) => (\\n"
    "                               <li key={service.id} className=\\"flex flex-wrap justify-between gap-2\\">\\n"
    "                                 <span>\\n"
    "                                   {service.type === \\"grinding\\"\\n"
    "                                     ? service.grindingProfile?.name ?? service.label ?? \\"آسیاب روستری\\"\\n"
    "                                     : service.label ?? \\"بسته‌بندی روستری\\"}\\n"
    "                                 </span>\\n"
    "                                 <span>{service.isFree ? \\"رایگان\\" : formatIrr(service.totalAmount)}</span>\\n"
    "                               </li>\\n"
    "                             ))}\\n"
    "                           </ul>\\n"
    "                         ) : null}",
)'''
text = text[:start] + replacement + text[end:]
path.write_text(text, encoding="utf-8")
print("R5F order UI anchor repaired")
