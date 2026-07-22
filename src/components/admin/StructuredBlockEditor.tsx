import type { ContentBlock } from "@/lib/api/content";

const inputClass =
  "w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-2.5 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

const blockLabels: Record<ContentBlock["type"], string> = {
  paragraph: "پاراگراف",
  heading: "تیتر",
  list: "فهرست",
  quote: "نقل‌قول",
  callout: "نکته برجسته",
  faq: "پرسش متداول",
  product_grid: "محصولات مرتبط",
  roastery_spotlight: "معرفی روستری",
  comparison_table: "جدول مقایسه",
};

function splitValues(value: string): string[] {
  return value
    .split(/[،,\n]/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function createBlock(type: ContentBlock["type"]): ContentBlock {
  switch (type) {
    case "paragraph":
      return { type, text: "" };
    case "heading":
      return { type, level: 2, text: "" };
    case "list":
      return { type, style: "unordered", items: [""] };
    case "quote":
      return { type, text: "", citation: null };
    case "callout":
      return { type, tone: "tip", text: "" };
    case "faq":
      return { type, items: [{ question: "", answer: "" }] };
    case "product_grid":
      return { type, product_slugs: [""] };
    case "roastery_spotlight":
      return { type, roastery_slug: "" };
    case "comparison_table":
      return {
        type,
        columns: ["ویژگی", "گزینه اول"],
        rows: [["", ""]],
      };
  }
}

function BlockField({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <label className="grid gap-2 text-xs font-bold text-[color:var(--steam)]">
      {label}
      {children}
    </label>
  );
}

function BlockInputs({
  block,
  onChange,
}: {
  block: ContentBlock;
  onChange: (block: ContentBlock) => void;
}) {
  switch (block.type) {
    case "paragraph":
      return (
        <BlockField label="متن پاراگراف">
          <textarea
            rows={5}
            value={block.text}
            onChange={(event) =>
              onChange({ ...block, text: event.target.value })
            }
            className={inputClass}
          />
        </BlockField>
      );
    case "heading":
      return (
        <div className="grid gap-3 sm:grid-cols-[8rem_1fr]">
          <BlockField label="سطح تیتر">
            <select
              value={block.level}
              onChange={(event) =>
                onChange({
                  ...block,
                  level: Number(event.target.value) as 2 | 3,
                })
              }
              className={inputClass}
            >
              <option value={2}>H2</option>
              <option value={3}>H3</option>
            </select>
          </BlockField>
          <BlockField label="متن تیتر">
            <input
              value={block.text}
              onChange={(event) =>
                onChange({ ...block, text: event.target.value })
              }
              className={inputClass}
            />
          </BlockField>
        </div>
      );
    case "list":
      return (
        <div className="grid gap-3 sm:grid-cols-[10rem_1fr]">
          <BlockField label="نوع فهرست">
            <select
              value={block.style}
              onChange={(event) =>
                onChange({
                  ...block,
                  style: event.target.value as "ordered" | "unordered",
                })
              }
              className={inputClass}
            >
              <option value="unordered">بدون شماره</option>
              <option value="ordered">شماره‌دار</option>
            </select>
          </BlockField>
          <BlockField label="هر مورد در یک خط">
            <textarea
              rows={5}
              value={block.items.join("\n")}
              onChange={(event) =>
                onChange({
                  ...block,
                  items: event.target.value.split("\n"),
                })
              }
              className={inputClass}
            />
          </BlockField>
        </div>
      );
    case "quote":
      return (
        <div className="grid gap-3">
          <BlockField label="متن نقل‌قول">
            <textarea
              rows={4}
              value={block.text}
              onChange={(event) =>
                onChange({ ...block, text: event.target.value })
              }
              className={inputClass}
            />
          </BlockField>
          <BlockField label="منبع یا گوینده">
            <input
              value={block.citation ?? ""}
              onChange={(event) =>
                onChange({
                  ...block,
                  citation: event.target.value || null,
                })
              }
              className={inputClass}
            />
          </BlockField>
        </div>
      );
    case "callout":
      return (
        <div className="grid gap-3 sm:grid-cols-[10rem_1fr]">
          <BlockField label="نوع پیام">
            <select
              value={block.tone}
              onChange={(event) =>
                onChange({
                  ...block,
                  tone: event.target.value as "info" | "tip" | "warning",
                })
              }
              className={inputClass}
            >
              <option value="info">اطلاعات</option>
              <option value="tip">نکته</option>
              <option value="warning">هشدار</option>
            </select>
          </BlockField>
          <BlockField label="متن پیام">
            <textarea
              rows={4}
              value={block.text}
              onChange={(event) =>
                onChange({ ...block, text: event.target.value })
              }
              className={inputClass}
            />
          </BlockField>
        </div>
      );
    case "faq":
      return (
        <div className="space-y-3">
          {block.items.map((item, itemIndex) => (
            <div
              key={itemIndex}
              className="grid gap-3 rounded-xl border border-[color:var(--mid)]/70 p-3"
            >
              <BlockField label={`سؤال ${itemIndex + 1}`}>
                <input
                  value={item.question}
                  onChange={(event) => {
                    const items = [...block.items];
                    items[itemIndex] = {
                      ...item,
                      question: event.target.value,
                    };
                    onChange({ ...block, items });
                  }}
                  className={inputClass}
                />
              </BlockField>
              <BlockField label="پاسخ">
                <textarea
                  rows={3}
                  value={item.answer}
                  onChange={(event) => {
                    const items = [...block.items];
                    items[itemIndex] = {
                      ...item,
                      answer: event.target.value,
                    };
                    onChange({ ...block, items });
                  }}
                  className={inputClass}
                />
              </BlockField>
              {block.items.length > 1 ? (
                <button
                  type="button"
                  onClick={() =>
                    onChange({
                      ...block,
                      items: block.items.filter((_, index) => index !== itemIndex),
                    })
                  }
                  className="justify-self-start text-xs text-red-300"
                >
                  حذف این پرسش
                </button>
              ) : null}
            </div>
          ))}
          {block.items.length < 30 ? (
            <button
              type="button"
              onClick={() =>
                onChange({
                  ...block,
                  items: [...block.items, { question: "", answer: "" }],
                })
              }
              className="text-xs font-bold text-[color:var(--roast)]"
            >
              + افزودن پرسش
            </button>
          ) : null}
        </div>
      );
    case "product_grid":
      return (
        <BlockField label="Slug محصولات با ویرگول جدا شوند">
          <input
            dir="ltr"
            value={block.product_slugs.join(", ")}
            onChange={(event) =>
              onChange({
                ...block,
                product_slugs: splitValues(event.target.value),
              })
            }
            className={`${inputClass} text-left`}
          />
        </BlockField>
      );
    case "roastery_spotlight":
      return (
        <BlockField label="Slug روستری">
          <input
            dir="ltr"
            value={block.roastery_slug}
            onChange={(event) =>
              onChange({ ...block, roastery_slug: event.target.value })
            }
            className={`${inputClass} text-left`}
          />
        </BlockField>
      );
    case "comparison_table":
      return (
        <div className="grid gap-3">
          <BlockField label="ستون‌ها با ویرگول جدا شوند">
            <input
              value={block.columns.join("، ")}
              onChange={(event) =>
                onChange({
                  ...block,
                  columns: splitValues(event.target.value),
                })
              }
              className={inputClass}
            />
          </BlockField>
          <BlockField label="هر ردیف در یک خط و سلول‌ها با | جدا شوند">
            <textarea
              rows={6}
              value={block.rows.map((row) => row.join(" | ")).join("\n")}
              onChange={(event) =>
                onChange({
                  ...block,
                  rows: event.target.value
                    .split("\n")
                    .filter((row) => row.trim())
                    .map((row) => row.split("|").map((cell) => cell.trim())),
                })
              }
              className={inputClass}
            />
          </BlockField>
          <p className="text-[11px] text-[color:var(--light)]">
            تعداد سلول‌های هر ردیف باید دقیقاً با تعداد ستون‌ها برابر باشد.
          </p>
        </div>
      );
  }
}

export function StructuredBlockEditor({
  blocks,
  onChange,
}: {
  blocks: ContentBlock[];
  onChange: (blocks: ContentBlock[]) => void;
}) {
  const update = (index: number, block: ContentBlock) => {
    const next = [...blocks];
    next[index] = block;
    onChange(next);
  };

  const move = (index: number, direction: -1 | 1) => {
    const destination = index + direction;
    if (destination < 0 || destination >= blocks.length) return;
    const next = [...blocks];
    [next[index], next[destination]] = [next[destination], next[index]];
    onChange(next);
  };

  return (
    <section>
      <div className="space-y-4">
        {blocks.map((block, index) => (
          <article
            key={`${block.type}-${index}`}
            className="rounded-2xl border border-[color:var(--mid)] bg-black/10 p-4"
          >
            <header className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-[color:var(--mid)]/60 pb-3">
              <div className="flex items-center gap-2">
                <span className="grid size-7 place-items-center rounded-full bg-[color:var(--roast)] text-xs font-bold text-[color:var(--night)]">
                  {index + 1}
                </span>
                <h3 className="text-sm font-bold">{blockLabels[block.type]}</h3>
              </div>
              <div className="flex gap-2 text-xs">
                <button
                  type="button"
                  disabled={index === 0}
                  onClick={() => move(index, -1)}
                  className="rounded-lg border border-[color:var(--mid)] px-2 py-1 disabled:opacity-30"
                >
                  بالا
                </button>
                <button
                  type="button"
                  disabled={index === blocks.length - 1}
                  onClick={() => move(index, 1)}
                  className="rounded-lg border border-[color:var(--mid)] px-2 py-1 disabled:opacity-30"
                >
                  پایین
                </button>
                <button
                  type="button"
                  disabled={blocks.length === 1}
                  onClick={() =>
                    onChange(blocks.filter((_, itemIndex) => itemIndex !== index))
                  }
                  className="rounded-lg border border-red-400/40 px-2 py-1 text-red-300 disabled:opacity-30"
                >
                  حذف
                </button>
              </div>
            </header>
            <BlockInputs block={block} onChange={(next) => update(index, next)} />
          </article>
        ))}
      </div>

      <div className="mt-5 rounded-2xl border border-dashed border-[color:var(--mid)] p-4">
        <p className="text-xs font-bold text-[color:var(--light)]">افزودن بلوک</p>
        <div className="mt-3 flex flex-wrap gap-2">
          {(Object.keys(blockLabels) as ContentBlock["type"][]).map((type) => (
            <button
              key={type}
              type="button"
              disabled={blocks.length >= 200}
              onClick={() => onChange([...blocks, createBlock(type)])}
              className="rounded-xl border border-[color:var(--mid)] px-3 py-2 text-xs font-bold text-[color:var(--steam)] transition hover:border-[color:var(--roast)] hover:text-[color:var(--roast)] disabled:opacity-40"
            >
              + {blockLabels[type]}
            </button>
          ))}
        </div>
      </div>
    </section>
  );
}
