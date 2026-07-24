const WEIGHTS = [50, 100, 250, 500, 1000] as const;
type Weight = (typeof WEIGHTS)[number];
import { formatWeight } from "@/lib/persian";

interface Props {
  value: Weight;
  onChange: (w: Weight) => void;
  size?: "sm" | "md";
}

export function WeightSelector({ value, onChange, size = "md" }: Props) {
  const btn = size === "sm" ? "px-2 py-1 text-[11px]" : "px-3 py-1.5 text-xs";
  return (
    <div role="radiogroup" aria-label="انتخاب وزن" className="flex flex-wrap gap-1.5">
      {WEIGHTS.map((w) => {
        const active = w === value;
        return (
          <button
            type="button"
            key={w}
            role="radio"
            aria-checked={active}
            onClick={() => onChange(w)}
            className={`rounded-lg border transition ${btn} ${
              active
                ? "border-[color:var(--rosta-primary)] bg-[color:var(--rosta-primary)] text-[color:var(--rosta-bg)]"
                : "border-[color:var(--rosta-border)] bg-[color:var(--rosta-card)] text-[color:var(--rosta-primary)] hover:border-[color:var(--rosta-accent)]"
            }`}
          >
            {formatWeight(w)}
          </button>
        );
      })}
    </div>
  );
}
