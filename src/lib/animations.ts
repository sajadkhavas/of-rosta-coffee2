import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { TextPlugin } from "gsap/TextPlugin";
import Lenis from "@studio-freight/lenis";

if (typeof window !== "undefined") {
  gsap.registerPlugin(ScrollTrigger, TextPlugin);
}

function prefersReducedMotion(): boolean {
  return (
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );
}

export function initLenis() {
  if (prefersReducedMotion()) return null;
  const lenis = new Lenis({
    duration: 1.4,
    easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    smoothWheel: true,
  });
  lenis.on("scroll", ScrollTrigger.update);
  gsap.ticker.add((time) => lenis.raf(time * 1000));
  gsap.ticker.lagSmoothing(0);
  return lenis;
}

export function splitTextReveal(selector: string, delay = 0) {
  const elements = document.querySelectorAll<HTMLElement>(selector);
  if (prefersReducedMotion()) {
    elements.forEach((element) => {
      element.style.opacity = "1";
      element.style.transform = "none";
    });
    return;
  }
  elements.forEach((el) => {
    if (el.dataset.splitDone === "1") return;
    const text = el.textContent || "";
    const words = text.trim().split(/\s+/);
    el.innerHTML = words
      .map(
        (word) =>
          `<span class="word-wrap" style="display:inline-block;overflow:hidden;vertical-align:top;"><span class="word-inner" style="display:inline-block;transform:translateY(110%);will-change:transform;">${word}</span></span>`,
      )
      .join(" ");
    el.dataset.splitDone = "1";
    gsap.to(el.querySelectorAll(".word-inner"), {
      y: 0,
      duration: 0.9,
      stagger: 0.06,
      ease: "power3.out",
      delay,
      scrollTrigger: { trigger: el, start: "top 85%" },
    });
  });
}

export function fadeUpStagger(selector: string, staggerDelay = 0.1) {
  const elements = document.querySelectorAll(selector);
  if (!elements.length) return;
  if (prefersReducedMotion()) {
    elements.forEach((element) => {
      if (element instanceof HTMLElement) {
        element.style.opacity = "1";
        element.style.transform = "none";
      }
    });
    return;
  }
  gsap.fromTo(
    elements,
    { opacity: 0, y: 60 },
    {
      opacity: 1,
      y: 0,
      duration: 0.8,
      stagger: staggerDelay,
      ease: "power3.out",
      scrollTrigger: { trigger: elements[0] as Element, start: "top 88%" },
    },
  );
}

export function animateCounter(el: Element, target: number, suffix = "") {
  if (prefersReducedMotion()) {
    el.textContent = target.toLocaleString("fa-IR") + suffix;
    return;
  }
  const obj = { val: 0 };
  gsap.to(obj, {
    val: target,
    duration: 2,
    ease: "power2.out",
    scrollTrigger: { trigger: el, start: "top 85%", once: true },
    onUpdate: () => {
      el.textContent = Math.round(obj.val).toLocaleString("fa-IR") + suffix;
    },
  });
}

export function magneticEffect(selector: string) {
  if (prefersReducedMotion()) return;
  document.querySelectorAll<HTMLElement>(selector).forEach((button) => {
    button.addEventListener("mousemove", (event) => {
      const rect = button.getBoundingClientRect();
      const x = event.clientX - rect.left - rect.width / 2;
      const y = event.clientY - rect.top - rect.height / 2;
      gsap.to(button, {
        x: x * 0.3,
        y: y * 0.3,
        duration: 0.3,
        ease: "power2.out",
      });
    });
    button.addEventListener("mouseleave", () => {
      gsap.to(button, {
        x: 0,
        y: 0,
        duration: 0.5,
        ease: "elastic.out(1, 0.5)",
      });
    });
  });
}

export function initCursor() {
  if (prefersReducedMotion()) return;
  if (window.matchMedia("(hover: none)").matches) return;
  if (document.getElementById("rosta-cursor")) return;

  const cursor = document.createElement("div");
  cursor.id = "rosta-cursor";
  cursor.style.cssText =
    "position:fixed;top:0;left:0;width:12px;height:12px;border-radius:50%;background:#C8965A;pointer-events:none;z-index:9999;transform:translate(-50%,-50%);transition:width .3s,height .3s,background .3s,border .3s;";

  const follower = document.createElement("div");
  follower.id = "rosta-follower";
  follower.style.cssText =
    "position:fixed;top:0;left:0;width:36px;height:36px;border-radius:50%;border:1px solid rgba(200,150,90,0.5);pointer-events:none;z-index:9998;transform:translate(-50%,-50%);";

  document.body.appendChild(cursor);
  document.body.appendChild(follower);
  document.documentElement.classList.add("cursor-enhanced");

  window.addEventListener("mousemove", (event) => {
    gsap.to(cursor, { x: event.clientX, y: event.clientY, duration: 0 });
    gsap.to(follower, {
      x: event.clientX,
      y: event.clientY,
      duration: 0.15,
    });
  });

  const bindHover = () => {
    document
      .querySelectorAll("button, a, [data-magnetic], input, [role=button]")
      .forEach((element) => {
        const target = element as HTMLElement;
        if (target.dataset.cursorBound === "1") return;
        target.dataset.cursorBound = "1";
        target.addEventListener("mouseenter", () => {
          gsap.to(cursor, {
            width: 48,
            height: 48,
            background: "rgba(200,150,90,0.2)",
            duration: 0.25,
          });
        });
        target.addEventListener("mouseleave", () => {
          gsap.to(cursor, {
            width: 12,
            height: 12,
            background: "#C8965A",
            duration: 0.25,
          });
        });
      });
  };

  bindHover();
  const observer = new MutationObserver(bindHover);
  observer.observe(document.body, { childList: true, subtree: true });
}
