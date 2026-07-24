type RoastLevel = "روشن" | "متوسط" | "تیره";

const styles: Record<RoastLevel, string> = {
  روشن: "bg-emerald-100 text-emerald-900 border-emerald-300",
  متوسط: "bg-amber-100 text-amber-900 border-amber-300",
  تیره: "bg-[#3D1A00] text-[#F5ECD7] border-[#3D1A00]",
};

export function RoastLevelBadge({ level }: { level: RoastLevel }) {
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${styles[level]}`}
    >
      رست {level}
    </span>
  );
}
