import { useEffect, useState } from "react";

type BrowserServiceWorkerRegistration = Awaited<
  ReturnType<ServiceWorkerContainer["register"]>
>;

function isSecureServiceWorkerContext(): boolean {
  return (
    window.location.protocol === "https:" ||
    ["localhost", "127.0.0.1"].includes(window.location.hostname)
  );
}

export function ServiceWorkerRegistration() {
  const [waitingWorker, setWaitingWorker] = useState<ServiceWorker | null>(null);

  useEffect(() => {
    if (
      !import.meta.env.PROD ||
      !("serviceWorker" in navigator) ||
      !isSecureServiceWorkerContext()
    ) {
      return;
    }

    let cancelled = false;
    let registration: BrowserServiceWorkerRegistration | undefined;

    const revealWaitingWorker = () => {
      if (!cancelled && registration?.waiting && navigator.serviceWorker.controller) {
        setWaitingWorker(registration.waiting);
      }
    };

    const register = async () => {
      try {
        registration = await navigator.serviceWorker.register("/sw.js", {
          scope: "/",
          updateViaCache: "none",
        });
        revealWaitingWorker();
        registration.addEventListener("updatefound", () => {
          const installing = registration?.installing;
          installing?.addEventListener("statechange", () => {
            if (installing.state === "installed") revealWaitingWorker();
          });
        });
        await registration.update().catch(() => undefined);
      } catch (error) {
        console.error("Service worker registration failed", error);
      }
    };

    void register();
    return () => {
      cancelled = true;
    };
  }, []);

  const activateUpdate = () => {
    if (!waitingWorker) return;
    navigator.serviceWorker.addEventListener(
      "controllerchange",
      () => window.location.reload(),
      { once: true },
    );
    waitingWorker.postMessage({ type: "ROSTA_SKIP_WAITING" });
  };

  if (!waitingWorker) return null;

  return (
    <aside
      role="status"
      aria-live="polite"
      className="fixed inset-x-4 bottom-20 z-[100] mx-auto flex max-w-lg items-center justify-between gap-4 rounded-2xl border border-[color:var(--roast)] bg-[color:var(--dark)] p-4 shadow-2xl md:bottom-6"
    >
      <div>
        <p className="text-sm font-bold">نسخه جدید رستا آماده است</p>
        <p className="mt-1 text-xs leading-6 text-[color:var(--light)]">
          برای جلوگیری از تغییر برنامه وسط سفارش، به‌روزرسانی فقط با تأیید شما اعمال می‌شود.
        </p>
      </div>
      <button
        type="button"
        onClick={activateUpdate}
        className="min-h-11 shrink-0 rounded-xl bg-[color:var(--roast)] px-4 text-xs font-bold text-[color:var(--night)]"
      >
        به‌روزرسانی
      </button>
    </aside>
  );
}
