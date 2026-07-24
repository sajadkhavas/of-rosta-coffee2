import Lenis from "lenis";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import type { PerformanceTier } from "./performance";

if (typeof window !== "undefined") {
  gsap.registerPlugin(ScrollTrigger);
}

type Cleanup = () => void;

function prefersReducedMotion(): boolean {
  return (
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );
}

function revealWithoutMotion(selector: string): void {
  document.querySelectorAll<HTMLElement>(selector).forEach((element) => {
    element.style.opacity = "1";
    element.style.transform = "none";
  });
}

export function initLenis(): Cleanup {
  if (prefersReducedMotion()) return () => undefined;

  const lenis = new Lenis({
    duration: 1.15,
    easing: (time) => Math.min(1, 1.001 - Math.pow(2, -10 * time)),
    smoothWheel: true,
  });
  const updateScrollTrigger = () => ScrollTrigger.update();
  const tick = (time: number) => lenis.raf(time * 1000);

  lenis.on("scroll", updateScrollTrigger);
  gsap.ticker.add(tick);
  gsap.ticker.lagSmoothing(0);

  return () => {
    lenis.off("scroll", updateScrollTrigger);
    gsap.ticker.remove(tick);
    lenis.destroy();
  };
}

export function splitTextReveal(selector: string, delay = 0): Cleanup {
  const elements = Array.from(document.querySelectorAll<HTMLElement>(selector));
  if (prefersReducedMotion()) {
    revealWithoutMotion(selector);
    return () => undefined;
  }

  const restorers: Cleanup[] = [];
  const tweens: Array<{ kill: () => void }> = [];

  elements.forEach((element) => {
    if (element.dataset.splitDone === "1") return;

    const originalText = element.textContent ?? "";
    const words = originalText.trim().split(/\s+/).filter(Boolean);
    if (words.length === 0) return;

    const fragment = document.createDocumentFragment();
    words.forEach((word, index) => {
      const wrapper = document.createElement("span");
      wrapper.className = "word-wrap";
      wrapper.style.cssText =
        "display:inline-block;overflow:hidden;vertical-align:top";

      const inner = document.createElement("span");
      inner.className = "word-inner";
      inner.style.cssText =
        "display:inline-block;transform:translateY(110%);will-change:transform";
      inner.textContent = word;
      wrapper.appendChild(inner);
      fragment.appendChild(wrapper);
      if (index < words.length - 1) fragment.append(" ");
    });

    element.replaceChildren(fragment);
    element.dataset.splitDone = "1";
    const tween = gsap.to(element.querySelectorAll(".word-inner"), {
      y: 0,
      duration: 0.8,
      stagger: 0.05,
      ease: "power3.out",
      delay,
      scrollTrigger: { trigger: element, start: "top 88%", once: true },
    });
    tweens.push(tween);
    restorers.push(() => {
      delete element.dataset.splitDone;
      element.textContent = originalText;
    });
  });

  return () => {
    tweens.forEach((tween) => tween.kill());
    restorers.forEach((restore) => restore());
  };
}

export function fadeUpStagger(
  selector: string,
  staggerDelay = 0.1,
): Cleanup {
  const elements = Array.from(document.querySelectorAll<HTMLElement>(selector));
  if (elements.length === 0) return () => undefined;
  if (prefersReducedMotion()) {
    revealWithoutMotion(selector);
    return () => undefined;
  }

  const tween = gsap.fromTo(
    elements,
    { opacity: 0, y: 44 },
    {
      opacity: 1,
      y: 0,
      duration: 0.72,
      stagger: staggerDelay,
      ease: "power3.out",
      clearProps: "transform,opacity,willChange",
      scrollTrigger: { trigger: elements[0], start: "top 90%", once: true },
    },
  );

  return () => tween.kill();
}

export function animateCounter(
  element: Element,
  target: number,
  suffix = "",
): Cleanup {
  if (prefersReducedMotion()) {
    element.textContent = target.toLocaleString("fa-IR") + suffix;
    return () => undefined;
  }

  const value = { current: 0 };
  const tween = gsap.to(value, {
    current: target,
    duration: 1.6,
    ease: "power2.out",
    scrollTrigger: { trigger: element, start: "top 88%", once: true },
    onUpdate: () => {
      element.textContent =
        Math.round(value.current).toLocaleString("fa-IR") + suffix;
    },
  });

  return () => tween.kill();
}

export function magneticEffect(selector: string): Cleanup {
  if (prefersReducedMotion()) return () => undefined;

  const cleanups: Cleanup[] = [];
  document.querySelectorAll<HTMLElement>(selector).forEach((button) => {
    const handleMove = (event: MouseEvent) => {
      const rect = button.getBoundingClientRect();
      gsap.to(button, {
        x: (event.clientX - rect.left - rect.width / 2) * 0.22,
        y: (event.clientY - rect.top - rect.height / 2) * 0.22,
        duration: 0.25,
        ease: "power2.out",
        overwrite: true,
      });
    };
    const handleLeave = () => {
      gsap.to(button, {
        x: 0,
        y: 0,
        duration: 0.4,
        ease: "power2.out",
        overwrite: true,
      });
    };

    button.addEventListener("mousemove", handleMove);
    button.addEventListener("mouseleave", handleLeave);
    cleanups.push(() => {
      button.removeEventListener("mousemove", handleMove);
      button.removeEventListener("mouseleave", handleLeave);
      gsap.killTweensOf(button);
      gsap.set(button, { clearProps: "transform" });
    });
  });

  return () => cleanups.forEach((cleanup) => cleanup());
}

export function initCursor(): Cleanup {
  if (prefersReducedMotion()) return () => undefined;
  if (!window.matchMedia("(hover: hover) and (pointer: fine)").matches) {
    return () => undefined;
  }
  if (document.getElementById("rosta-cursor")) return () => undefined;

  const cursor = document.createElement("div");
  cursor.id = "rosta-cursor";
  cursor.setAttribute("aria-hidden", "true");
  cursor.style.cssText =
    "position:fixed;top:0;left:0;width:12px;height:12px;border-radius:50%;background:#C8965A;pointer-events:none;z-index:9999;transform:translate(-50%,-50%);transition:width .25s,height .25s,background .25s";

  const follower = document.createElement("div");
  follower.id = "rosta-follower";
  follower.setAttribute("aria-hidden", "true");
  follower.style.cssText =
    "position:fixed;top:0;left:0;width:36px;height:36px;border-radius:50%;border:1px solid rgba(200,150,90,.5);pointer-events:none;z-index:9998;transform:translate(-50%,-50%)";

  document.body.append(cursor, follower);
  document.documentElement.classList.add("cursor-enhanced");

  const handleMove = (event: MouseEvent) => {
    gsap.set(cursor, { x: event.clientX, y: event.clientY });
    gsap.to(follower, {
      x: event.clientX,
      y: event.clientY,
      duration: 0.14,
      overwrite: true,
    });
  };
  window.addEventListener("mousemove", handleMove, { passive: true });

  const boundTargets = new Map<HTMLElement, [EventListener, EventListener]>();
  const bindHoverTargets = () => {
    document
      .querySelectorAll<HTMLElement>(
        "button, a, [data-magnetic], input, select, textarea, [role=button]",
      )
      .forEach((target) => {
        if (boundTargets.has(target)) return;
        const enter = () => {
          gsap.to(cursor, {
            width: 44,
            height: 44,
            background: "rgba(200,150,90,.2)",
            duration: 0.2,
            overwrite: true,
          });
        };
        const leave = () => {
          gsap.to(cursor, {
            width: 12,
            height: 12,
            background: "#C8965A",
            duration: 0.2,
            overwrite: true,
          });
        };
        target.addEventListener("mouseenter", enter);
        target.addEventListener("mouseleave", leave);
        boundTargets.set(target, [enter, leave]);
      });
  };

  bindHoverTargets();
  const observer = new MutationObserver(bindHoverTargets);
  observer.observe(document.body, { childList: true, subtree: true });

  return () => {
    observer.disconnect();
    window.removeEventListener("mousemove", handleMove);
    boundTargets.forEach(([enter, leave], target) => {
      target.removeEventListener("mouseenter", enter);
      target.removeEventListener("mouseleave", leave);
    });
    gsap.killTweensOf([cursor, follower]);
    cursor.remove();
    follower.remove();
    document.documentElement.classList.remove("cursor-enhanced");
  };
}

export function initPageAnimations(options: {
  tier: PerformanceTier;
}): Cleanup {
  const { tier } = options;
  if (tier === "minimal" || prefersReducedMotion()) {
    revealWithoutMotion("[data-split-text], [data-fade-up], .r-card");
    return () => undefined;
  }

  const cleanups: Cleanup[] = [
    splitTextReveal("[data-split-text]"),
    fadeUpStagger("[data-fade-up]", 0.07),
    fadeUpStagger(".r-card", 0.08),
    initCursor(),
  ];

  document.querySelectorAll<HTMLElement>("[data-counter]").forEach((element) => {
    const target = Number.parseInt(element.dataset.counter ?? "0", 10);
    if (Number.isFinite(target)) {
      cleanups.push(
        animateCounter(element, target, element.dataset.suffix ?? ""),
      );
    }
  });

  if (tier === "full") {
    cleanups.push(initLenis(), magneticEffect("[data-magnetic]"));
  }

  ScrollTrigger.refresh();
  return () => {
    cleanups.reverse().forEach((cleanup) => cleanup());
  };
}
