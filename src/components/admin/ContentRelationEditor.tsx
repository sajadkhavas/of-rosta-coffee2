import type { ContentRelationInput } from "@/lib/api/admin-content";

const inputClass =
  "w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-2.5 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

const relationLabels: Record<ContentRelationInput["relation_type"], string> = {
  related: "مرتبط",
  mentions: "ذکر شده",
  recommends: "پیشنهاد می‌کند",
  compares: "مقایسه می‌کند",
  primary_topic: "موضوع اصلی",
};

const targetLabels: Record<ContentRelationInput["target_type"], string> = {
  content: "محتوا",
  product: "محصول",
  roastery: "روستری",
  origin: "خاستگاه",
  brew_method: "روش دم‌آوری",
  taste: "طعم",
};

function emptyRelation(position: number): ContentRelationInput {
  return {
    relation_type: "related",
    target_type: "content",
    target_key: "",
    anchor_text: null,
    position,
  };
}

export function ContentRelationEditor({
  relations,
  onChange,
}: {
  relations: ContentRelationInput[];
  onChange: (relations: ContentRelationInput[]) => void;
}) {
  const update = (index: number, relation: ContentRelationInput) => {
    const next = [...relations];
    next[index] = relation;
    onChange(next.map((item, position) => ({ ...item, position })));
  };

  const move = (index: number, direction: -1 | 1) => {
    const destination = index + direction;
    if (destination < 0 || destination >= relations.length) return;
    const next = [...relations];
    [next[index], next[destination]] = [next[destination], next[index]];
    onChange(next.map((item, position) => ({ ...item, position })));
  };

  return (
    <section>
      <div className="space-y-3">
        {relations.map((relation, index) => (
          <article
            key={index}
            className="rounded-2xl border border-[color:var(--mid)] bg-black/10 p-4"
          >
            <header className="mb-3 flex flex-wrap items-center justify-between gap-3">
              <p className="text-xs font-bold text-[color:var(--roast)]">
                رابطه {index + 1}
              </p>
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
                  disabled={index === relations.length - 1}
                  onClick={() => move(index, 1)}
                  className="rounded-lg border border-[color:var(--mid)] px-2 py-1 disabled:opacity-30"
                >
                  پایین
                </button>
                <button
                  type="button"
                  onClick={() =>
                    onChange(
                      relations
                        .filter((_, itemIndex) => itemIndex !== index)
                        .map((item, position) => ({ ...item, position })),
                    )
                  }
                  className="rounded-lg border border-red-400/40 px-2 py-1 text-red-300"
                >
                  حذف
                </button>
              </div>
            </header>

            <div className="grid gap-3 md:grid-cols-2">
              <label className="grid gap-2 text-xs font-bold">
                نوع رابطه
                <select
                  value={relation.relation_type}
                  onChange={(event) =>
                    update(index, {
                      ...relation,
                      relation_type: event.target
                        .value as ContentRelationInput["relation_type"],
                    })
                  }
                  className={inputClass}
                >
                  {(
                    Object.keys(relationLabels) as ContentRelationInput["relation_type"][]
                  ).map((value) => (
                    <option key={value} value={value}>
                      {relationLabels[value]}
                    </option>
                  ))}
                </select>
              </label>
              <label className="grid gap-2 text-xs font-bold">
                نوع مقصد
                <select
                  value={relation.target_type}
                  onChange={(event) =>
                    update(index, {
                      ...relation,
                      target_type: event.target
                        .value as ContentRelationInput["target_type"],
                    })
                  }
                  className={inputClass}
                >
                  {(
                    Object.keys(targetLabels) as ContentRelationInput["target_type"][]
                  ).map((value) => (
                    <option key={value} value={value}>
                      {targetLabels[value]}
                    </option>
                  ))}
                </select>
              </label>
              <label className="grid gap-2 text-xs font-bold">
                Slug یا کلید مقصد
                <input
                  required
                  dir="ltr"
                  value={relation.target_key}
                  onChange={(event) =>
                    update(index, {
                      ...relation,
                      target_key: event.target.value,
                    })
                  }
                  className={`${inputClass} text-left`}
                />
              </label>
              <label className="grid gap-2 text-xs font-bold">
                متن لینک
                <input
                  value={relation.anchor_text ?? ""}
                  onChange={(event) =>
                    update(index, {
                      ...relation,
                      anchor_text: event.target.value || null,
                    })
                  }
                  className={inputClass}
                />
              </label>
            </div>
          </article>
        ))}
      </div>

      <button
        type="button"
        disabled={relations.length >= 100}
        onClick={() =>
          onChange([...relations, emptyRelation(relations.length)])
        }
        className="mt-4 rounded-xl border border-dashed border-[color:var(--roast)] px-4 py-2.5 text-xs font-bold text-[color:var(--roast)] disabled:opacity-40"
      >
        + افزودن رابطه داخلی
      </button>
    </section>
  );
}
