import { useEffect, useRef, useState } from "react";

export function NetworkStatus() {
  const [online, setOnline] = useState(true);
  const [showRestored, setShowRestored] = useState(false);
  const wasOffline = useRef(false);

  useEffect(() => {
    setOnline(navigator.onLine);
    wasOffline.current = !navigator.onLine;

    let restoredTimer: number | undefined;
    const handleOffline = () => {
      wasOffline.current = true;
      setShowRestored(false);
      setOnline(false);
    };
    const handleOnline = () => {
      setOnline(true);
      if (!wasOffline.current) return;
      wasOffline.current = false;
      setShowRestored(true);
      restoredTimer = window.setTimeout(() => setShowRestored(false), 4500);
    };

    window.addEventListener("offline", handleOffline);
    window.addEventListener("online", handleOnline);
    return () => {
      window.removeEventListener("offline", handleOffline);
      window.removeEventListener("online", handleOnline);
      if (restoredTimer) window.clearTimeout(restoredTimer);
    };
  }, []);

  if (online && !showRestored) return null;

  return (
    <aside
      role="status"
      aria-live="polite"
      className={`fixed inset-x-4 bottom-20 z-[95] mx-auto flex max-w-lg items-center justify-between gap-4 rounded-2xl border p-4 shadow-2xl md:bottom-6 ${
        online
          ? "border-emerald-700 bg-emerald-950 text-emerald-50"
          : "border-[color:var(--roast)] bg-[color:var(--dark)] text-[color:var(--steam)]"
      }`}
    >
      <div>
        <p className="text-sm font-bold">
          {online ? "اتصال اینترنت برقرار شد" : "اتصال اینترنت قطع است"}
        </p>
        <p className="mt-1 text-xs leading-6 opacity-85">
          {online
            ? "اطلاعات تازه دوباره از سرور دریافت می‌شود."
            : "صفحات ذخیره‌نشده و عملیات خرید تا بازگشت اتصال در دسترس نیستند."}
        </p>
      </div>
      {!online ? (
        <button
          type="button"
          onClick={() => window.location.reload()}
          className="min-h-11 shrink-0 rounded-xl border border-[color:var(--roast)] px-4 text-xs font-bold"
        >
          تلاش مجدد
        </button>
      ) : null}
    </aside>
  );
}
