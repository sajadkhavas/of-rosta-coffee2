import { useEffect, useRef } from "react";

export function Particles({ count = 30 }: { count?: number }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    el.innerHTML = "";
    for (let i = 0; i < count; i++) {
      const p = document.createElement("span");
      p.className = "particle";
      const size = 2 + Math.random() * 4;
      p.style.width = `${size}px`;
      p.style.height = `${size}px`;
      p.style.left = `${Math.random() * 100}%`;
      p.style.bottom = `-${Math.random() * 20}px`;
      p.style.animationDuration = `${8 + Math.random() * 10}s`;
      p.style.animationDelay = `${-Math.random() * 12}s`;
      p.style.opacity = `${0.2 + Math.random() * 0.4}`;
      el.appendChild(p);
    }
  }, [count]);

  return (
    <div ref={ref} aria-hidden className="pointer-events-none absolute inset-0 overflow-hidden" />
  );
}

export function CoffeeBean3D() {
  return (
    <div
      aria-hidden
      className="relative mx-auto"
      style={{ perspective: "800px", width: "min(340px, 70vw)", height: "min(340px, 70vw)" }}
    >
      <div className="bean-3d absolute inset-0 grid place-items-center">
        <svg
          viewBox="0 0 200 200"
          className="h-full w-full drop-shadow-[0_20px_40px_rgba(200,150,90,0.35)]"
        >
          <defs>
            <radialGradient id="beanGrad" cx="35%" cy="30%" r="75%">
              <stop offset="0%" stopColor="#e8b876" />
              <stop offset="45%" stopColor="#c8965a" />
              <stop offset="100%" stopColor="#3a1e08" />
            </radialGradient>
            <linearGradient id="beanShine" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stopColor="rgba(255,240,220,0.6)" />
              <stop offset="60%" stopColor="rgba(255,240,220,0)" />
            </linearGradient>
          </defs>
          <ellipse cx="100" cy="100" rx="62" ry="90" fill="url(#beanGrad)" />
          <path
            d="M100 20 C 92 60, 92 140, 100 180 C 108 140, 108 60, 100 20 Z"
            fill="#1a0a00"
            opacity="0.85"
          />
          <ellipse cx="80" cy="70" rx="18" ry="34" fill="url(#beanShine)" />
        </svg>
      </div>
    </div>
  );
}
