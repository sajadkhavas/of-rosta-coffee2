export type PerformanceTier = "full" | "balanced" | "minimal";

export interface PerformanceSignals {
  reducedMotion: boolean;
  saveData: boolean;
  effectiveType?: string;
  deviceMemory?: number;
  hardwareConcurrency?: number;
  pointerFine: boolean;
}

export type WebVitalName = "CLS" | "FCP" | "INP" | "LCP" | "TTFB";
export type WebVitalRating = "good" | "needs-improvement" | "poor";

export interface WebVitalMetric {
  name: WebVitalName;
  value: number;
  delta: number;
  rating: WebVitalRating;
  id: string;
  path: string;
}

interface NavigatorConnectionLike {
  saveData?: boolean;
  effectiveType?: string;
}

interface NavigatorWithPerformanceHints extends Navigator {
  connection?: NavigatorConnectionLike;
  deviceMemory?: number;
}

interface LargestContentfulPaintEntry extends PerformanceEntry {
  renderTime?: number;
  loadTime?: number;
}

interface LayoutShiftEntry extends PerformanceEntry {
  value: number;
  hadRecentInput: boolean;
}

interface InteractionEntry extends PerformanceEntry {
  duration: number;
  interactionId?: number;
}

const THRESHOLDS: Record<WebVitalName, readonly [number, number]> = {
  CLS: [0.1, 0.25],
  FCP: [1800, 3000],
  INP: [200, 500],
  LCP: [2500, 4000],
  TTFB: [800, 1800],
};

const CURSOR_POLICY_ID = "rosta-native-cursor-fallback";

function ensureNativeCursorFallback(): void {
  if (typeof document === "undefined") return;
  if (document.getElementById(CURSOR_POLICY_ID)) return;

  const style = document.createElement("style");
  style.id = CURSOR_POLICY_ID;
  style.textContent = `
    @media (prefers-reduced-motion: no-preference) and (hover: hover) and (pointer: fine) {
      html:not(.cursor-enhanced),
      html:not(.cursor-enhanced) body,
      html:not(.cursor-enhanced) * {
        cursor: auto !important;
      }
    }
  `;
  document.head.appendChild(style);
}

export function classifyPerformanceTier(signals: PerformanceSignals): PerformanceTier {
  const effectiveType = signals.effectiveType?.toLowerCase();
  const constrainedConnection = effectiveType === "slow-2g" || effectiveType === "2g";
  const constrainedDevice =
    (typeof signals.deviceMemory === "number" && signals.deviceMemory <= 2) ||
    (typeof signals.hardwareConcurrency === "number" && signals.hardwareConcurrency <= 2);

  if (signals.reducedMotion || signals.saveData || constrainedConnection || constrainedDevice) {
    return "minimal";
  }

  const moderateConnection = effectiveType === "3g";
  const moderateDevice =
    (typeof signals.deviceMemory === "number" && signals.deviceMemory <= 4) ||
    (typeof signals.hardwareConcurrency === "number" && signals.hardwareConcurrency <= 4);

  if (moderateConnection || moderateDevice || !signals.pointerFine) {
    return "balanced";
  }

  return "full";
}

export function getBrowserPerformanceTier(): PerformanceTier {
  if (typeof window === "undefined" || typeof navigator === "undefined") {
    return "minimal";
  }

  ensureNativeCursorFallback();

  const browserNavigator = navigator as NavigatorWithPerformanceHints;
  return classifyPerformanceTier({
    reducedMotion: window.matchMedia("(prefers-reduced-motion: reduce)").matches,
    saveData: browserNavigator.connection?.saveData === true,
    effectiveType: browserNavigator.connection?.effectiveType,
    deviceMemory: browserNavigator.deviceMemory,
    hardwareConcurrency: browserNavigator.hardwareConcurrency,
    pointerFine: window.matchMedia("(hover: hover) and (pointer: fine)").matches,
  });
}

export function shouldEnableEnhancedMotion(tier = getBrowserPerformanceTier()): boolean {
  return tier === "full";
}

export function scheduleIdleTask(task: () => void, timeout = 1200): () => void {
  if (typeof window === "undefined") return () => undefined;

  const idleWindow = window as Window & {
    requestIdleCallback?: (callback: IdleRequestCallback, options?: IdleRequestOptions) => number;
    cancelIdleCallback?: (handle: number) => void;
  };

  if (idleWindow.requestIdleCallback) {
    const handle = idleWindow.requestIdleCallback(() => task(), { timeout });
    return () => idleWindow.cancelIdleCallback?.(handle);
  }

  const handle = window.setTimeout(task, Math.min(timeout, 350));
  return () => window.clearTimeout(handle);
}

export function rateWebVital(name: WebVitalName, value: number): WebVitalRating {
  const [good, poor] = THRESHOLDS[name];
  if (value <= good) return "good";
  if (value <= poor) return "needs-improvement";
  return "poor";
}

function createMetric(name: WebVitalName, value: number, previousValue = 0): WebVitalMetric {
  return {
    name,
    value,
    delta: Math.max(0, value - previousValue),
    rating: rateWebVital(name, value),
    id: `rosta-${name.toLowerCase()}-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
    path: typeof window === "undefined" ? "/" : window.location.pathname,
  };
}

function publishMetric(metric: WebVitalMetric, onMetric?: (metric: WebVitalMetric) => void) {
  onMetric?.(metric);

  if (typeof window !== "undefined") {
    window.dispatchEvent(new CustomEvent<WebVitalMetric>("rosta:web-vital", { detail: metric }));
  }

  const endpoint = import.meta.env.VITE_PERFORMANCE_ENDPOINT as string | undefined;
  if (!endpoint || typeof navigator === "undefined") return;

  try {
    const target = new URL(endpoint, window.location.origin);
    if (target.origin !== window.location.origin) return;
    const payload = JSON.stringify(metric);
    void fetch(target, {
      method: "POST",
      body: payload,
      headers: { "content-type": "application/json" },
      keepalive: true,
      credentials: "omit",
    }).catch(() => undefined);
  } catch {
    // Metrics must never affect the customer experience.
  }
}

function observe(
  type: string,
  callback: PerformanceObserverCallback,
): PerformanceObserver | undefined {
  if (
    typeof PerformanceObserver === "undefined" ||
    !PerformanceObserver.supportedEntryTypes.includes(type)
  ) {
    return undefined;
  }

  try {
    const observer = new PerformanceObserver(callback);
    observer.observe({ type, buffered: true });
    return observer;
  } catch {
    return undefined;
  }
}

export function startWebVitals(options?: {
  onMetric?: (metric: WebVitalMetric) => void;
}): () => void {
  if (typeof window === "undefined" || typeof performance === "undefined") {
    return () => undefined;
  }

  const observers: PerformanceObserver[] = [];
  const published = new Map<WebVitalName, number>();
  let clsValue = 0;
  let inpValue = 0;
  let finalized = false;

  const publish = (name: WebVitalName, value: number) => {
    const previousValue = published.get(name) ?? 0;
    if (value <= 0 || value === previousValue) return;
    published.set(name, value);
    publishMetric(createMetric(name, value, previousValue), options?.onMetric);
  };

  const navigation = performance.getEntriesByType("navigation")[0] as
    | PerformanceNavigationTiming
    | undefined;
  if (navigation?.responseStart) publish("TTFB", navigation.responseStart);

  const paintObserver = observe("paint", (list) => {
    for (const entry of list.getEntries()) {
      if (entry.name === "first-contentful-paint") publish("FCP", entry.startTime);
    }
  });
  if (paintObserver) observers.push(paintObserver);

  const lcpObserver = observe("largest-contentful-paint", (list) => {
    const entries = list.getEntries();
    const last = entries.at(-1) as LargestContentfulPaintEntry | undefined;
    if (!last) return;
    publish("LCP", last.renderTime || last.loadTime || last.startTime);
  });
  if (lcpObserver) observers.push(lcpObserver);

  const clsObserver = observe("layout-shift", (list) => {
    for (const entry of list.getEntries() as LayoutShiftEntry[]) {
      if (!entry.hadRecentInput) clsValue += entry.value;
    }
  });
  if (clsObserver) observers.push(clsObserver);

  const inpObserver = observe("event", (list) => {
    for (const entry of list.getEntries() as InteractionEntry[]) {
      if ((entry.interactionId ?? 0) > 0) {
        inpValue = Math.max(inpValue, entry.duration);
      }
    }
  });
  if (inpObserver) observers.push(inpObserver);

  const finalize = () => {
    if (finalized) return;
    finalized = true;
    publish("CLS", clsValue);
    publish("INP", inpValue);
  };

  const handleVisibilityChange = () => {
    if (document.visibilityState === "hidden") finalize();
  };
  document.addEventListener("visibilitychange", handleVisibilityChange);
  window.addEventListener("pagehide", finalize, { once: true });

  return () => {
    finalize();
    observers.forEach((observer) => observer.disconnect());
    document.removeEventListener("visibilitychange", handleVisibilityChange);
    window.removeEventListener("pagehide", finalize);
  };
}
