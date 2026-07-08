import { roastDateLabel } from "@/lib/persian";

export function RoastDateBadge({ daysAgo }: { daysAgo: number }) {
  return (
    <span
      className="inline-flex items-center gap-1 rounded-full bg-[color:var(--rosta-accent)] px-3 py-1 text-xs font-bold text-white shadow-sm"
      title="تاریخ برشته‌کاری"
    >
      <span aria-hidden>🔥</span>
      {roastDateLabel(daysAgo)}
    </span>
  );
}
