export function toFa(n: number | string): string {
  return String(n).replace(/[0-9]/g, (d) => "۰۱۲۳۴۵۶۷۸۹"[Number(d)]);
}

export function formatToman(n: number): string {
  return `${n.toLocaleString("fa-IR")} تومان`;
}

export function formatIrr(n: number): string {
  return `${n.toLocaleString("fa-IR")} ریال`;
}

export function formatWeight(g: number): string {
  if (g >= 1000) return `${toFa(g / 1000)} کیلوگرم`;
  return `${toFa(g)} گرم`;
}

export function roastDateLabel(daysAgo: number): string {
  if (daysAgo === 0) return "رست امروز";
  if (daysAgo === 1) return "رست دیروز";
  return `رست ${toFa(daysAgo)} روز پیش`;
}
