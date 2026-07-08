import { GRINDS, type Grind } from "@/data/seed";

interface Props {
  value: Grind;
  onChange: (g: Grind) => void;
}

export function GrindSelector({ value, onChange }: Props) {
  return (
    <div role="radiogroup" aria-label="انتخاب آسیاب" className="flex flex-wrap gap-1.5">
      {GRINDS.map((g) => {
        const active = g === value;
        return (
          <button
            type="button"
            key={g}
            role="radio"
            aria-checked={active}
            onClick={() => onChange(g)}
            className={`rounded-lg border px-3 py-1.5 text-xs transition ${
              active
                ? "border-[color:var(--rosta-primary)] bg-[color:var(--rosta-primary)] text-[color:var(--rosta-bg)]"
                : "border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] text-[color:var(--rosta-primary)] hover:border-[color:var(--rosta-accent)]"
            }`}
          >
            {g}
          </button>
        );
      })}
    </div>
  );
}
