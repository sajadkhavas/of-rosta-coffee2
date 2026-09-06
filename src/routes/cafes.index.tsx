import { useQuery } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { listCafes } from "@/lib/api/cafes";
import { isApiError } from "@/lib/api/client";

export const Route = createFileRoute("/cafes/")({
  head: () => ({
    meta: [
      { title: "کافه‌های نزدیک من | رستا" },
      {
        name: "description",
        content: "کافه‌های تأییدشده رستا را بر اساس شهر یا فاصله از موقعیت خود پیدا کنید.",
      },
    ],
  }),
  component: CafeDirectoryPage,
});
function CafeDirectoryPage() {
  const [city, setCity] = useState("");
  const [position, setPosition] = useState<{ lat: number; lng: number } | null>(null);
  const [radius, setRadius] = useState(10);
  const [locationError, setLocationError] = useState("");
  const query = useQuery({
    queryKey: ["cafes", "directory", city, position, radius],
    queryFn: () =>
      listCafes({
        city: city || undefined,
        lat: position?.lat,
        lng: position?.lng,
        radiusKm: position ? radius : undefined,
      }),
    staleTime: 60_000,
  });
  const locate = () => {
    if (!navigator.geolocation) {
      setLocationError("مرورگر شما موقعیت مکانی را پشتیبانی نمی‌کند.");
      return;
    }
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setPosition({ lat: coords.latitude, lng: coords.longitude });
        setLocationError("");
      },
      () => setLocationError("دسترسی به موقعیت داده نشد. می‌توانید شهر را وارد کنید."),
      { enableHighAccuracy: false, timeout: 10_000, maximumAge: 300_000 },
    );
  };
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-10" dir="rtl">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="text-xs font-bold tracking-[.18em] text-[color:var(--roast)]">
              CAFE DIRECTORY
            </p>
            <h1 className="mt-2 text-3xl font-bold">کافه‌های نزدیک شما</h1>
            <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
              فقط کافه‌های تأییدشده نمایش داده می‌شوند. موقعیت شما فقط برای همین جستجو به API ارسال
              می‌شود و در این صفحه ذخیره نمی‌شود.
            </p>
          </div>
          <div className="flex gap-2">
            <Link
              to="/cafes/apply"
              className="rounded-xl bg-[color:var(--roast)] px-4 py-3 text-sm font-bold text-[color:var(--night)]"
            >
              ثبت کافه
            </Link>
            <Link
              to="/cafes/portal"
              className="rounded-xl border border-[color:var(--mid)] px-4 py-3 text-sm"
            >
              پنل کافه من
            </Link>
          </div>
        </div>
        <div className="mt-8 grid gap-3 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4 md:grid-cols-[1fr_auto_auto]">
          <input
            value={city}
            onChange={(e) => setCity(e.target.value)}
            placeholder="شهر؛ مثلاً کرج"
            className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3"
          />
          <select
            value={radius}
            onChange={(e) => setRadius(Number(e.target.value))}
            className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3"
          >
            <option value={1}>۱ کیلومتر</option>
            <option value={3}>۳ کیلومتر</option>
            <option value={5}>۵ کیلومتر</option>
            <option value={10}>۱۰ کیلومتر</option>
            <option value={25}>۲۵ کیلومتر</option>
            <option value={50}>۵۰ کیلومتر</option>
          </select>
          <button
            type="button"
            onClick={locate}
            className="rounded-xl border border-[color:var(--roast)] px-4 py-2 text-sm font-bold"
          >
            استفاده از موقعیت من
          </button>
        </div>
        {locationError ? <p className="mt-3 text-sm text-amber-300">{locationError}</p> : null}
        {query.isLoading ? <p className="mt-8">در حال دریافت کافه‌ها…</p> : null}
        {query.isError ? (
          <p className="mt-8 text-red-300">
            {isApiError(query.error) ? query.error.message : "فهرست کافه‌ها دریافت نشد."}
          </p>
        ) : null}
        <section className="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {query.data?.map((cafe) => (
            <Link
              key={cafe.id}
              to="/cafes/$slug"
              params={{ slug: cafe.slug }}
              className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 transition hover:border-[color:var(--roast)]"
            >
              <div className="flex items-start justify-between gap-3">
                <h2 className="text-xl font-bold">{cafe.name}</h2>
                {cafe.distance_km != null ? (
                  <span className="text-xs text-[color:var(--roast)]">
                    {cafe.distance_km.toLocaleString("fa-IR")} km
                  </span>
                ) : null}
              </div>
              <p className="mt-2 text-sm text-[color:var(--light)]">{cafe.city}</p>
              <p className="mt-3 line-clamp-2 text-sm leading-7">{cafe.address}</p>
              {cafe.amenities.length ? (
                <div className="mt-4 flex flex-wrap gap-2">
                  {cafe.amenities.slice(0, 4).map((item) => (
                    <span
                      key={item}
                      className="rounded-full bg-[color:var(--night)] px-3 py-1 text-xs"
                    >
                      {item}
                    </span>
                  ))}
                </div>
              ) : null}
            </Link>
          ))}
        </section>
        {!query.isLoading && query.data?.length === 0 ? (
          <p className="mt-10 text-center text-[color:var(--light)]">
            کافه تأییدشده‌ای در این محدوده پیدا نشد.
          </p>
        ) : null}
      </main>
      <Footer />
    </>
  );
}
