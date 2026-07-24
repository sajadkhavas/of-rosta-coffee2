import { useEffect, useRef, useState } from "react";

type BrowserServiceWorkerRegistration = Awaited<ReturnType<ServiceWorkerContainer["register"]>>;

const UPDATE_INTERVAL_MS = 60 * 60 * 1000;

function isSecureServiceWorkerContext(): boolean {
  return (
    window.location.protocol === "https:" ||
    ["localhost", "127.0.0.1"].includes(window.location.hostname)
  );
}

export function ServiceWorkerRegistration() {
  const [waitingWorker, setWaitingWorker] = useState<ServiceWorker | null>(null);
  const [dismissed, setDismissed] = useState(false);
  const reloadRequested = useRef(false);

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
    let interval: number | undefined;

    const revealWaitingWorker = () => {
      if (!cancelled && registration?.waiting && navigator.serviceWorker.controller) {
        setDismissed(false);
        setWaitingWorker(registration.waiting);
      }
    };

    const handleUpdateFound = () => {
      const installing = registration?.installing;
      if (!installing) return;
      const handleStateChange = () => {
        if (installing.state !== "installed") return;
        installing.removeEventListener("statechange", handleStateChange);
        revealWaitingWorker();
      };
      installing.addEventListener("statechange", handleStateChange);
    };

    const checkForUpdate = () => {
      if (document.visibilityState === "visible" && navigator.onLine) {
        void registration?.update().catch(() => undefined);
      }
    };

    const register = async () => {
      try {
        registration = await navigator.serviceWorker.register("/sw.js", {
          scope: "/",
          updateViaCache: "none",
        });
        if (cancelled) return;
        revealWaitingWorker();
        registration.addEventListener("updatefound", handleUpdateFound);
        window.addEventListener("online", checkForUpdate);
        document.addEventListener("visibilitychange", checkForUpdate);
        interval = window.setInterval(checkForUpdate, UPDATE_INTERVAL_MS);
        checkForUpdate();
      } catch (error) {
        console.error("Service worker registration failed", error);
      }
    };

    void register();
    return () => {
      cancelled = true;
      registration?.removeEventListener("updatefound", handleUpdateFound);
      window.removeEventListener("online", checkForUpdate);
      document.removeEventListener("visibilitychange", checkForUpdate);
      if (interval) window.clearInterval(interval);
    };
  }, []);

  const activateUpdate = () => {
    if (!waitingWorker || reloadRequested.current) return;
    reloadRequested.current = true;
    navigator.serviceWorker.addEventListener("controllerchange", () => window.location.reload(), {
      once: true,
    });
    waitingWorker.postMessage({ type: "ROSTA_SKIP_WAITING" });
  };

  if (!waitingWorker || dismissed) return null;

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
      <div className="flex shrink-0 flex-col gap-2 sm:flex-row">
        <button
          type="button"
          onClick={() => setDismissed(true)}
          className="min-h-11 rounded-xl border border-[color:var(--mid)] px-4 text-xs font-bold"
        >
          بعداً
        </button>
        <button
          type="button"
          onClick={activateUpdate}
          className="min-h-11 rounded-xl bg-[color:var(--roast)] px-4 text-xs font-bold text-[color:var(--night)]"
        >
          به‌روزرسانی
        </button>
      </div>
    </aside>
  );
}
