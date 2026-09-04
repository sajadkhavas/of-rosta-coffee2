from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding='utf-8')


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    content = target.read_text(encoding='utf-8')
    count = content.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one match, found {count}: {old[:90]!r}')
    target.write_text(content.replace(old, new, 1), encoding='utf-8')

# Evolve the permanent business audit: authoritative pricing now means a server-side resolver,
# not a literal direct read of ProductVariant::price.
replace_once(
    'backend/scripts/audit-business-contract.php',
    '        "\'unit_price\' => \\$variant->price" => \'Quotes must use authoritative prices.\',\n',
    '        \'$this->wholesalePricing->resolve($user, $variant, $quantity)\' => \'Quotes must resolve authoritative retail/B2B prices server-side.\',\n'
    '        "\'unit_price\' => \\$resolved[\'unit_price\']" => \'Quotes must persist the authoritative resolved unit price.\',\n',
)
replace_once(
    'backend/scripts/audit-business-contract.php',
    "    'app/Services/Checkout/CouponService.php' => [\n",
    "    'app/Services/B2B/WholesalePricingService.php' => [\n"
    "        'variant->weight_grams' => 'Wholesale pricing must derive weight from the authoritative variant.',\n"
    "        'wholesaleTiers' => 'Wholesale pricing must resolve persisted seller tiers.',\n"
    "        'CafeStatus::Verified' => 'Wholesale eligibility must require a verified cafe.',\n"
    "        'ps12-wholesale-tier-v1' => 'Wholesale pricing snapshots must be versioned.',\n"
    "    ],\n"
    "    'app/Services/Checkout/CouponService.php' => [\n",
)
# Seller product responses should carry loaded tier tables as well.
replace_once(
    'backend/app/Http/Controllers/Seller/SellerProductController.php',
    "                'variants',\n",
    "                'variants.wholesaleTiers' => static fn ($query) => $query->orderBy('min_weight_grams'),\n",
)
replace_once(
    'backend/app/Http/Controllers/Seller/SellerProductController.php',
    "            'variants',\n        ]);\n",
    "            'variants.wholesaleTiers' => static fn ($query) => $query->orderBy('min_weight_grams'),\n        ]);\n",
)
# Cleanup + safer optional review note.
replace_once('backend/app/Services/Cafe/CafeService.php', "use Illuminate\\Database\\QueryException;\n", "")
replace_once(
    'backend/app/Http/Controllers/Admin/AdminCafeController.php',
    "$request->validated('review_note'), $request);",
    "$request->input('review_note'), $request);",
)

# Frontend strict contracts: wholesale tiers are public pricing information; applied pricing is quote-only.
replace_once(
    'src/lib/api/contracts.ts',
    "export interface ProductVariant {\n",
    "export interface WholesalePriceTier {\n  minWeightGrams: 5000 | 10000 | 20000 | 50000;\n  unitPrice: number;\n}\n\nexport interface ProductVariantPricing {\n  version: 'ps12-wholesale-tier-v1';\n  mode: 'retail' | 'wholesale';\n  retailUnitPrice: number;\n  appliedUnitPrice: number;\n  totalWeightGrams: number;\n  cafeId?: string | null;\n  tierMinWeightGrams?: number | null;\n}\n\nexport interface ProductVariant {\n",
)
replace_once(
    'src/lib/api/contracts.ts',
    "  availableQuantity?: number | null;\n}\n",
    "  availableQuantity?: number | null;\n  wholesaleTiers?: WholesalePriceTier[];\n  pricing?: ProductVariantPricing | null;\n}\n",
)
replace_once(
    'src/lib/api/schemas.ts',
    "    available_quantity: z.number().int().nonnegative().max(1_000_000).nullable().optional(),\n",
    "    available_quantity: z.number().int().nonnegative().max(1_000_000).nullable().optional(),\n"
    "    wholesale_tiers: z.array(z.object({\n"
    "      min_weight_grams: z.union([z.literal(5000), z.literal(10000), z.literal(20000), z.literal(50000)]),\n"
    "      unit_price: moneySchema,\n"
    "    }).strict()).max(4).optional(),\n"
    "    pricing: z.object({\n"
    "      version: z.literal('ps12-wholesale-tier-v1'),\n"
    "      mode: z.enum(['retail', 'wholesale']),\n"
    "      retail_unit_price: moneySchema,\n"
    "      applied_unit_price: moneySchema,\n"
    "      variant_weight_grams: z.number().int().positive().max(1000),\n"
    "      quantity: z.number().int().min(1).max(1000),\n"
    "      total_weight_grams: z.number().int().positive().max(10_000_000),\n"
    "      cafe_id: identifierSchema.nullable(),\n"
    "      tier_id: identifierSchema.nullable(),\n"
    "      tier_min_weight_grams: z.number().int().positive().nullable(),\n"
    "    }).strict().optional(),\n",
)
replace_once('src/lib/api/schemas.ts', "    quantity: z.number().int().min(1).max(20),\n", "    quantity: z.number().int().min(1).max(1000),\n")

for path in ['src/lib/api/catalog.ts', 'src/lib/api/seller-operations.ts']:
    replace_once(
        path,
        "    availableQuantity: value.available_quantity ?? null,\n",
        "    availableQuantity: value.available_quantity ?? null,\n"
        "    wholesaleTiers: value.wholesale_tiers?.map((tier) => ({ minWeightGrams: tier.min_weight_grams, unitPrice: tier.unit_price })) ?? [],\n"
        "    pricing: value.pricing ? {\n"
        "      version: value.pricing.version,\n"
        "      mode: value.pricing.mode,\n"
        "      retailUnitPrice: value.pricing.retail_unit_price,\n"
        "      appliedUnitPrice: value.pricing.applied_unit_price,\n"
        "      totalWeightGrams: value.pricing.total_weight_grams,\n"
        "      cafeId: value.pricing.cafe_id,\n"
        "      tierMinWeightGrams: value.pricing.tier_min_weight_grams,\n"
        "    } : null,\n",
    )

write('src/lib/api/cafes.ts', r'''import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract, resourceSchema } from "./schemas";

const cafeStatus = z.enum(["pending", "verified", "suspended", "rejected"]);
const cafeSchema = z.object({
  id: z.string().min(1),
  name: z.string().min(1).max(160),
  slug: z.string().min(1).max(180),
  status: cafeStatus,
  is_verified: z.boolean(),
  city: z.string().min(1).max(120),
  address: z.string().min(1).max(1000),
  latitude: z.number().nullable(),
  longitude: z.number().nullable(),
  phone: z.string().nullable(),
  website_url: z.string().nullable(),
  instagram_handle: z.string().nullable(),
  description: z.string().nullable(),
  opening_hours: z.record(z.string(), z.unknown()).or(z.array(z.unknown())).default([]),
  amenities: z.array(z.string()).max(50).default([]),
  verified_at: z.string().nullable(),
  distance_km: z.number().nonnegative().nullable().optional(),
  membership_role: z.enum(["owner", "manager"]).optional(),
}).strict();
const listSchema = z.object({ data: z.object({ items: z.array(cafeSchema).max(200), pagination: z.unknown().optional() }).passthrough() }).passthrough();

export type Cafe = z.infer<typeof cafeSchema>;
export type CafeStatus = z.infer<typeof cafeStatus>;
export interface CafeInput {
  name: string; slug?: string; city: string; address: string;
  latitude?: number | null; longitude?: number | null; phone?: string | null;
  websiteUrl?: string | null; instagramHandle?: string | null; description?: string | null;
}
function body(input: CafeInput) {
  return {
    name: input.name.trim(), slug: input.slug?.trim() || undefined, city: input.city.trim(), address: input.address.trim(),
    latitude: input.latitude ?? null, longitude: input.longitude ?? null, phone: input.phone?.trim() || null,
    website_url: input.websiteUrl?.trim() || null, instagram_handle: input.instagramHandle?.trim() || null,
    description: input.description?.trim() || null,
  };
}
export async function listCafes(params: { city?: string; lat?: number; lng?: number; radiusKm?: number } = {}): Promise<Cafe[]> {
  const search = new URLSearchParams();
  if (params.city) search.set("city", params.city);
  if (params.lat !== undefined && params.lng !== undefined) { search.set("lat", String(params.lat)); search.set("lng", String(params.lng)); }
  if (params.radiusKm !== undefined) search.set("radius_km", String(params.radiusKm));
  const response = parseContract(listSchema, await apiFetch<unknown>(`/cafes${search.size ? `?${search}` : ""}`), "فهرست کافه‌ها");
  return response.data.items;
}
export async function getCafe(slug: string): Promise<Cafe> {
  const response = parseContract(resourceSchema(cafeSchema), await apiFetch<unknown>(`/cafes/${encodeURIComponent(slug)}`), "صفحه کافه");
  return response.data;
}
export async function applyCafe(input: CafeInput): Promise<Cafe> {
  const response = parseContract(resourceSchema(cafeSchema), await apiFetch<unknown>("/cafes/apply", { method: "POST", body: body(input) }), "ثبت درخواست کافه");
  return response.data;
}
export async function listMyCafes(): Promise<Cafe[]> {
  const response = parseContract(listSchema, await apiFetch<unknown>("/me/cafes"), "کافه‌های من");
  return response.data.items;
}
export async function updateCafe(id: string, input: CafeInput): Promise<Cafe> {
  const response = parseContract(resourceSchema(cafeSchema), await apiFetch<unknown>(`/me/cafes/${encodeURIComponent(id)}`, { method: "PATCH", body: body(input) }), "ویرایش کافه");
  return response.data;
}
export async function listAdminCafes(status: CafeStatus = "pending"): Promise<Cafe[]> {
  const response = parseContract(listSchema, await apiFetch<unknown>(`/admin/cafes?status=${status}&per_page=100`), "بررسی کافه‌ها");
  return response.data.items;
}
export async function setCafeStatus(id: string, status: CafeStatus, reviewNote?: string): Promise<Cafe> {
  const response = parseContract(resourceSchema(cafeSchema), await apiFetch<unknown>(`/admin/cafes/${encodeURIComponent(id)}/status`, { method: "PATCH", body: { status, review_note: reviewNote?.trim() || null } }), "تغییر وضعیت کافه");
  return response.data;
}
export const myCafesQueryOptions = () => queryOptions({ queryKey: ["cafes", "mine"], queryFn: listMyCafes, staleTime: 30_000 });
''')

write('src/lib/api/wholesale.ts', r'''import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract } from "./schemas";

const threshold = z.union([z.literal(5000), z.literal(10000), z.literal(20000), z.literal(50000)]);
const tier = z.object({ id: z.string().min(1), min_weight_grams: threshold, unit_price: z.number().int().positive(), is_active: z.boolean() }).strict();
const responseSchema = z.object({ data: z.object({ items: z.array(tier).max(4) }).strict() }).passthrough();
export type WholesaleThreshold = z.infer<typeof threshold>;
export interface WholesaleTier { id?: string; minWeightGrams: WholesaleThreshold; unitPrice: number; isActive: boolean }
function path(roasteryId: string, productId: string, variantId: string) { return `/seller/roasteries/${encodeURIComponent(roasteryId)}/products/${encodeURIComponent(productId)}/variants/${encodeURIComponent(variantId)}/wholesale-tiers`; }
export async function getWholesaleTiers(roasteryId: string, productId: string, variantId: string): Promise<WholesaleTier[]> {
  const response = parseContract(responseSchema, await apiFetch<unknown>(path(roasteryId, productId, variantId)), "قیمت عمده");
  return response.data.items.map((item) => ({ id: item.id, minWeightGrams: item.min_weight_grams, unitPrice: item.unit_price, isActive: item.is_active }));
}
export async function replaceWholesaleTiers(roasteryId: string, productId: string, variantId: string, tiers: WholesaleTier[]): Promise<WholesaleTier[]> {
  const response = parseContract(responseSchema, await apiFetch<unknown>(path(roasteryId, productId, variantId), { method: "PUT", body: { tiers: tiers.map((item) => ({ min_weight_grams: item.minWeightGrams, unit_price: item.unitPrice, is_active: item.isActive })) } }), "ذخیره قیمت عمده");
  return response.data.items.map((item) => ({ id: item.id, minWeightGrams: item.min_weight_grams, unitPrice: item.unit_price, isActive: item.is_active }));
}
''')

write('src/routes/cafes.tsx', r'''import { createFileRoute, Outlet } from "@tanstack/react-router";
export const Route = createFileRoute("/cafes")({ component: Outlet });
''')

write('src/routes/cafes.index.tsx', r'''import { useQuery } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useState } from "react";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { listCafes } from "@/lib/api/cafes";
import { isApiError } from "@/lib/api/client";

export const Route = createFileRoute("/cafes/")({
  head: () => ({ meta: [{ title: "کافه‌های نزدیک من | رستا" }, { name: "description", content: "کافه‌های تأییدشده رستا را بر اساس شهر یا فاصله از موقعیت خود پیدا کنید." }] }),
  component: CafeDirectoryPage,
});
function CafeDirectoryPage() {
  const [city, setCity] = useState("");
  const [position, setPosition] = useState<{lat:number;lng:number}|null>(null);
  const [radius, setRadius] = useState(10);
  const [locationError, setLocationError] = useState("");
  const query = useQuery({ queryKey: ["cafes", "directory", city, position, radius], queryFn: () => listCafes({ city: city || undefined, lat: position?.lat, lng: position?.lng, radiusKm: position ? radius : undefined }), staleTime: 60_000 });
  const locate = () => {
    if (!navigator.geolocation) { setLocationError("مرورگر شما موقعیت مکانی را پشتیبانی نمی‌کند."); return; }
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => { setPosition({ lat: coords.latitude, lng: coords.longitude }); setLocationError(""); },
      () => setLocationError("دسترسی به موقعیت داده نشد. می‌توانید شهر را وارد کنید."),
      { enableHighAccuracy: false, timeout: 10_000, maximumAge: 300_000 },
    );
  };
  return <><Navbar /><main className="mx-auto max-w-7xl px-4 py-10" dir="rtl">
    <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-bold tracking-[.18em] text-[color:var(--roast)]">CAFE DIRECTORY</p><h1 className="mt-2 text-3xl font-bold">کافه‌های نزدیک شما</h1><p className="mt-3 text-sm leading-7 text-[color:var(--light)]">فقط کافه‌های تأییدشده نمایش داده می‌شوند. موقعیت شما فقط برای همین جستجو به API ارسال می‌شود و در این صفحه ذخیره نمی‌شود.</p></div><div className="flex gap-2"><Link to="/cafes/apply" className="rounded-xl bg-[color:var(--roast)] px-4 py-3 text-sm font-bold text-[color:var(--night)]">ثبت کافه</Link><Link to="/cafes/portal" className="rounded-xl border border-[color:var(--mid)] px-4 py-3 text-sm">پنل کافه من</Link></div></div>
    <div className="mt-8 grid gap-3 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4 md:grid-cols-[1fr_auto_auto]">
      <input value={city} onChange={(e)=>setCity(e.target.value)} placeholder="شهر؛ مثلاً کرج" className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3" />
      <select value={radius} onChange={(e)=>setRadius(Number(e.target.value))} className="min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3"><option value={1}>۱ کیلومتر</option><option value={3}>۳ کیلومتر</option><option value={5}>۵ کیلومتر</option><option value={10}>۱۰ کیلومتر</option><option value={25}>۲۵ کیلومتر</option><option value={50}>۵۰ کیلومتر</option></select>
      <button type="button" onClick={locate} className="rounded-xl border border-[color:var(--roast)] px-4 py-2 text-sm font-bold">استفاده از موقعیت من</button>
    </div>
    {locationError ? <p className="mt-3 text-sm text-amber-300">{locationError}</p> : null}
    {query.isLoading ? <p className="mt-8">در حال دریافت کافه‌ها…</p> : null}
    {query.isError ? <p className="mt-8 text-red-300">{isApiError(query.error) ? query.error.message : "فهرست کافه‌ها دریافت نشد."}</p> : null}
    <section className="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">{query.data?.map((cafe)=><Link key={cafe.id} to="/cafes/$slug" params={{slug:cafe.slug}} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 transition hover:border-[color:var(--roast)]"><div className="flex items-start justify-between gap-3"><h2 className="text-xl font-bold">{cafe.name}</h2>{cafe.distance_km != null ? <span className="text-xs text-[color:var(--roast)]">{cafe.distance_km.toLocaleString("fa-IR")} km</span> : null}</div><p className="mt-2 text-sm text-[color:var(--light)]">{cafe.city}</p><p className="mt-3 line-clamp-2 text-sm leading-7">{cafe.address}</p>{cafe.amenities.length ? <div className="mt-4 flex flex-wrap gap-2">{cafe.amenities.slice(0,4).map((item)=><span key={item} className="rounded-full bg-[color:var(--night)] px-3 py-1 text-xs">{item}</span>)}</div> : null}</Link>)}</section>
    {!query.isLoading && query.data?.length===0 ? <p className="mt-10 text-center text-[color:var(--light)]">کافه تأییدشده‌ای در این محدوده پیدا نشد.</p> : null}
  </main><Footer /></>;
}
''')

write('src/routes/cafes.$slug.tsx', r'''import { useQuery } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { getCafe } from "@/lib/api/cafes";
export const Route = createFileRoute("/cafes/$slug")({ component: CafePage });
function CafePage() {
  const { slug } = Route.useParams();
  const query = useQuery({ queryKey:["cafe",slug], queryFn:()=>getCafe(slug) });
  return <><Navbar /><main className="mx-auto max-w-4xl px-4 py-10" dir="rtl">{query.data ? <article className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6 md:p-8"><Link to="/cafes" className="text-sm text-[color:var(--roast)]">← همه کافه‌ها</Link><h1 className="mt-5 text-3xl font-bold">{query.data.name}</h1><p className="mt-2 text-sm text-[color:var(--light)]">{query.data.city} · کافه تأییدشده رستا</p>{query.data.description ? <p className="mt-6 leading-8">{query.data.description}</p> : null}<dl className="mt-8 grid gap-4 sm:grid-cols-2"><div><dt className="text-xs text-[color:var(--light)]">آدرس</dt><dd className="mt-1">{query.data.address}</dd></div>{query.data.phone ? <div><dt className="text-xs text-[color:var(--light)]">تماس</dt><dd className="mt-1">{query.data.phone}</dd></div> : null}{query.data.instagram_handle ? <div><dt className="text-xs text-[color:var(--light)]">اینستاگرام</dt><dd className="mt-1">@{query.data.instagram_handle.replace(/^@/,"")}</dd></div> : null}{query.data.website_url ? <div><dt className="text-xs text-[color:var(--light)]">وب‌سایت</dt><dd className="mt-1"><a href={query.data.website_url} rel="noreferrer" target="_blank" className="text-[color:var(--roast)]">مشاهده وب‌سایت</a></dd></div> : null}</dl>{query.data.amenities.length ? <div className="mt-8 flex flex-wrap gap-2">{query.data.amenities.map((item)=><span key={item} className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-sm">{item}</span>)}</div> : null}</article> : <p>{query.isError ? "کافه پیدا نشد." : "در حال دریافت…"}</p>}</main><Footer /></>;
}
''')

write('src/routes/cafes.apply.tsx', r'''import { useMutation } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { applyCafe } from "@/lib/api/cafes";
import { isApiError } from "@/lib/api/client";
export const Route = createFileRoute("/cafes/apply")({ head:()=>({meta:[{title:"ثبت کافه | رستا"},{name:"robots",content:"noindex,nofollow"}]}), component: ApplyPage });
const field="min-h-11 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3";
function ApplyPage(){return <><Navbar/><main className="mx-auto max-w-3xl px-4 py-10" dir="rtl"><AccountGuard>{()=> <ApplyForm/>}</AccountGuard></main><Footer/></>}
function ApplyForm(){const [form,setForm]=useState({name:"",slug:"",city:"",address:"",phone:"",description:"",latitude:null as number|null,longitude:null as number|null}); const mutation=useMutation({mutationFn:()=>applyCafe(form)}); const submit=(e:FormEvent)=>{e.preventDefault();mutation.mutate()}; const locate=()=>navigator.geolocation?.getCurrentPosition(({coords})=>setForm((v)=>({...v,latitude:coords.latitude,longitude:coords.longitude}))); if(mutation.data)return <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6"><h1 className="text-2xl font-bold">درخواست ثبت شد</h1><p className="mt-3 leading-7">{mutation.data.name} با وضعیت «در انتظار بررسی» ثبت شد. قیمت عمده پس از تأیید کافه فعال می‌شود.</p><Link to="/cafes/portal" className="mt-5 inline-block text-[color:var(--roast)]">رفتن به پنل کافه</Link></div>; return <form onSubmit={submit} className="space-y-5 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6"><h1 className="text-3xl font-bold">ثبت کافه در رستا</h1><p className="text-sm leading-7 text-[color:var(--light)]">بعد از ثبت، کافه Pending می‌شود و فقط پس از تأیید ادمین در فهرست عمومی و قیمت همکاری فعال خواهد شد.</p><label className="block text-sm">نام کافه<input required value={form.name} onChange={(e)=>setForm({...form,name:e.target.value})} className={`${field} mt-2`}/></label><label className="block text-sm">شناسه انگلیسی اختیاری<input value={form.slug} onChange={(e)=>setForm({...form,slug:e.target.value})} className={`${field} mt-2`} placeholder="my-cafe"/></label><div className="grid gap-4 sm:grid-cols-2"><label className="text-sm">شهر<input required value={form.city} onChange={(e)=>setForm({...form,city:e.target.value})} className={`${field} mt-2`}/></label><label className="text-sm">شماره تماس<input value={form.phone} onChange={(e)=>setForm({...form,phone:e.target.value})} className={`${field} mt-2`}/></label></div><label className="block text-sm">آدرس<textarea required value={form.address} onChange={(e)=>setForm({...form,address:e.target.value})} className={`${field} mt-2 min-h-24 py-3`}/></label><label className="block text-sm">توضیحات<textarea value={form.description} onChange={(e)=>setForm({...form,description:e.target.value})} className={`${field} mt-2 min-h-24 py-3`}/></label><div className="flex flex-wrap items-center gap-3"><button type="button" onClick={locate} className="rounded-xl border border-[color:var(--mid)] px-4 py-3 text-sm">ثبت موقعیت فعلی</button>{form.latitude!=null?<span className="text-xs text-[color:var(--light)]">مختصات ثبت شد</span>:null}</div>{mutation.isError?<p className="text-sm text-red-300">{isApiError(mutation.error)?mutation.error.message:"ثبت درخواست انجام نشد."}</p>:null}<button disabled={mutation.isPending} className="rounded-xl bg-[color:var(--roast)] px-5 py-3 font-bold text-[color:var(--night)]">{mutation.isPending?"در حال ثبت…":"ارسال برای بررسی"}</button></form>}
''')

write('src/routes/cafes.portal.tsx', r'''import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { myCafesQueryOptions, updateCafe, type Cafe } from "@/lib/api/cafes";
import { useState, type FormEvent } from "react";
export const Route=createFileRoute("/cafes/portal")({head:()=>({meta:[{title:"پنل کافه | رستا"},{name:"robots",content:"noindex,nofollow"}]}),component:Page});
const labels={pending:"در انتظار بررسی",verified:"تأییدشده",suspended:"تعلیق",rejected:"ردشده"} as const;
function Page(){return <><Navbar/><main className="mx-auto max-w-5xl px-4 py-10" dir="rtl"><AccountGuard>{()=> <Portal/>}</AccountGuard></main><Footer/></>}
function Portal(){const q=useQuery(myCafesQueryOptions());return <section><div className="flex items-center justify-between gap-4"><div><h1 className="text-3xl font-bold">پنل کافه</h1><p className="mt-2 text-sm text-[color:var(--light)]">پروفایل، وضعیت تأیید و دسترسی خرید عمده کافه را اینجا مدیریت کنید.</p></div><Link to="/cafes/apply" className="rounded-xl border border-[color:var(--mid)] px-4 py-3 text-sm">ثبت کافه جدید</Link></div><div className="mt-8 space-y-4">{q.data?.map((c)=><CafeCard key={c.id} cafe={c}/>)}{!q.isLoading&&q.data?.length===0?<p>هنوز کافه‌ای ثبت نکرده‌اید.</p>:null}</div></section>}
function CafeCard({cafe}:{cafe:Cafe}){const qc=useQueryClient();const [edit,setEdit]=useState(false);const [form,setForm]=useState({name:cafe.name,city:cafe.city,address:cafe.address,phone:cafe.phone??"",description:cafe.description??""});const m=useMutation({mutationFn:()=>updateCafe(cafe.id,form),onSuccess:()=>{setEdit(false);void qc.invalidateQueries({queryKey:["cafes","mine"]})}});const submit=(e:FormEvent)=>{e.preventDefault();m.mutate()};return <article className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><div className="flex flex-wrap justify-between gap-3"><div><h2 className="text-xl font-bold">{cafe.name}</h2><p className="mt-1 text-sm text-[color:var(--light)]">{labels[cafe.status]} · نقش شما: {cafe.membership_role==="manager"?"مدیر":"مالک"}</p></div><button onClick={()=>setEdit(!edit)} className="text-sm text-[color:var(--roast)]">{edit?"بستن":"ویرایش"}</button></div>{cafe.status==="verified"?<p className="mt-4 rounded-xl bg-emerald-950/30 p-3 text-sm text-emerald-200">قیمت‌های عمده ۵/۱۰/۲۰/۵۰ کیلو برای این حساب فعال است.</p>:<p className="mt-4 rounded-xl bg-amber-950/30 p-3 text-sm text-amber-200">تا زمان تأیید، خرید با قیمت تک‌فروشی انجام می‌شود.</p>}{edit?<form onSubmit={submit} className="mt-5 grid gap-3"><input value={form.name} onChange={e=>setForm({...form,name:e.target.value})} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"/><input value={form.city} onChange={e=>setForm({...form,city:e.target.value})} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"/><textarea value={form.address} onChange={e=>setForm({...form,address:e.target.value})} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"/><input value={form.phone} onChange={e=>setForm({...form,phone:e.target.value})} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"/><textarea value={form.description} onChange={e=>setForm({...form,description:e.target.value})} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3"/><button disabled={m.isPending} className="justify-self-start rounded-xl bg-[color:var(--roast)] px-4 py-2 font-bold text-[color:var(--night)]">ذخیره</button></form>:null}</article>}
''')

write('src/routes/admin.cafes.tsx', r'''import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useState } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { listAdminCafes, setCafeStatus, type CafeStatus } from "@/lib/api/cafes";
export const Route=createFileRoute("/admin/cafes")({head:()=>({meta:[{title:"مدیریت کافه‌ها | رستا"},{name:"robots",content:"noindex,nofollow"}]}),component:Page});
const statuses:[CafeStatus,string][]=[["pending","در انتظار"],["verified","تأییدشده"],["suspended","تعلیق"],["rejected","ردشده"]];
function Page(){return <><Navbar/><main className="mx-auto max-w-6xl px-4 py-10" dir="rtl"><AccountGuard>{u=>u.roles.includes("administrator")?<Admin/>:<Navigate to="/forbidden" replace/>}</AccountGuard></main><Footer/></>}
function Admin(){const [status,setStatus]=useState<CafeStatus>("pending");const qc=useQueryClient();const q=useQuery({queryKey:["admin","cafes",status],queryFn:()=>listAdminCafes(status)});const m=useMutation({mutationFn:({id,next}:{id:string;next:CafeStatus})=>setCafeStatus(id,next),onSuccess:()=>void qc.invalidateQueries({queryKey:["admin","cafes"]})});return <section><h1 className="text-3xl font-bold">مدیریت کافه‌ها</h1><p className="mt-2 text-sm text-[color:var(--light)]">تأیید کافه هم‌زمان دسترسی B2B را فعال می‌کند؛ تعلیق یا رد آن دسترسی را برمی‌دارد.</p><div className="mt-6 flex flex-wrap gap-2">{statuses.map(([v,l])=><button key={v} onClick={()=>setStatus(v)} className={`rounded-xl px-4 py-2 text-sm ${status===v?"bg-[color:var(--roast)] text-[color:var(--night)]":"border border-[color:var(--mid)]"}`}>{l}</button>)}</div><div className="mt-6 space-y-3">{q.data?.map(c=><article key={c.id} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><div className="flex flex-wrap items-start justify-between gap-4"><div><h2 className="font-bold">{c.name}</h2><p className="mt-1 text-sm text-[color:var(--light)]">{c.city} · {c.address}</p></div><div className="flex flex-wrap gap-2">{c.status!=="verified"?<button onClick={()=>m.mutate({id:c.id,next:"verified"})} className="rounded-lg bg-emerald-700 px-3 py-2 text-sm">تأیید</button>:null}{c.status!=="suspended"?<button onClick={()=>m.mutate({id:c.id,next:"suspended"})} className="rounded-lg border border-amber-600 px-3 py-2 text-sm">تعلیق</button>:null}{c.status!=="rejected"?<button onClick={()=>m.mutate({id:c.id,next:"rejected"})} className="rounded-lg border border-red-700 px-3 py-2 text-sm">رد</button>:null}</div></div></article>)}{!q.isLoading&&q.data?.length===0?<p>موردی در این وضعیت وجود ندارد.</p>:null}</div></section>}
''')

write('src/routes/panel.wholesale.tsx', r'''import { useMutation, useQuery } from "@tanstack/react-query";
import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { listSellerProducts, listSellerRoasteries } from "@/lib/api/seller-operations";
import { getWholesaleTiers, replaceWholesaleTiers, type WholesaleThreshold, type WholesaleTier } from "@/lib/api/wholesale";
import { formatIrr } from "@/lib/persian";
export const Route=createFileRoute("/panel/wholesale")({head:()=>({meta:[{title:"قیمت عمده | پنل روستری"},{name:"robots",content:"noindex,nofollow"}]}),component:Page});
const thresholds:WholesaleThreshold[]=[5000,10000,20000,50000];
function Page(){return <><Navbar/><main className="mx-auto max-w-7xl px-4 py-10" dir="rtl"><AccountGuard>{()=> <Workspace/>}</AccountGuard></main><Footer/></>}
function Workspace(){const roasteries=useQuery({queryKey:["seller","roasteries"],queryFn:listSellerRoasteries});const [rid,setRid]=useState("");useEffect(()=>{if(!rid&&roasteries.data?.[0])setRid(roasteries.data[0].id)},[rid,roasteries.data]);const products=useQuery({queryKey:["seller","products",rid],queryFn:()=>listSellerProducts(rid),enabled:Boolean(rid)});return <section><h1 className="text-3xl font-bold">قیمت‌گذاری عمده کافه‌ها</h1><p className="mt-3 max-w-3xl text-sm leading-7 text-[color:var(--light)]">برای هر وزن محصول، قیمت واحد در مرزهای ۵، ۱۰، ۲۰ و ۵۰ کیلو را تعیین کنید. فقط کافه تأییدشده این قیمت را در Checkout دریافت می‌کند.</p><select value={rid} onChange={e=>setRid(e.target.value)} className="mt-6 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3">{roasteries.data?.map(r=><option key={r.id} value={r.id}>{r.name}</option>)}</select><div className="mt-8 space-y-5">{products.data?.items.map(p=><article key={p.id} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><h2 className="text-xl font-bold">{p.name}</h2><div className="mt-4 grid gap-4 xl:grid-cols-2">{p.variants.map(v=><VariantForm key={v.id} roasteryId={rid} productId={p.id} variant={v}/>)}</div></article>)}</div></section>}
function VariantForm({roasteryId,productId,variant}:{roasteryId:string;productId:string;variant:{id:string;weightGrams:number;price:number}}){const q=useQuery({queryKey:["wholesale",variant.id],queryFn:()=>getWholesaleTiers(roasteryId,productId,variant.id)});const [values,setValues]=useState<Record<number,string>>({});useEffect(()=>{if(q.data)setValues(Object.fromEntries(q.data.map(t=>[t.minWeightGrams,String(t.unitPrice)])))},[q.data]);const m=useMutation({mutationFn:(tiers:WholesaleTier[])=>replaceWholesaleTiers(roasteryId,productId,variant.id,tiers)});const submit=(e:FormEvent)=>{e.preventDefault();const tiers=thresholds.filter(t=>values[t]?.trim()).map(t=>({minWeightGrams:t,unitPrice:Number(values[t]),isActive:true}));m.mutate(tiers)};return <form onSubmit={submit} className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"><div className="flex justify-between gap-3"><strong>{variant.weightGrams.toLocaleString("fa-IR")} گرم</strong><span className="text-xs text-[color:var(--light)]">تک: {formatIrr(variant.price)}</span></div><div className="mt-4 grid gap-3 sm:grid-cols-2">{thresholds.map(t=><label key={t} className="text-xs">از {(t/1000).toLocaleString("fa-IR")} کیلو<input inputMode="numeric" value={values[t]??""} onChange={e=>setValues({...values,[t]:e.target.value.replace(/\D/g,"")})} placeholder="قیمت واحد IRR" className="mt-1 min-h-10 w-full rounded-lg border border-[color:var(--mid)] bg-[color:var(--dark)] px-2"/></label>)}</div>{m.isError?<p className="mt-3 text-xs text-red-300">قیمت‌ها باید از قیمت تک بیشتر نباشند و با افزایش وزن ثابت یا کمتر شوند.</p>:null}{m.isSuccess?<p className="mt-3 text-xs text-emerald-300">ذخیره شد.</p>:null}<button className="mt-4 rounded-lg bg-[color:var(--roast)] px-4 py-2 text-sm font-bold text-[color:var(--night)]">ذخیره پله‌ها</button></form>}
''')

# Make new workspaces discoverable from existing admin/seller dashboards.
replace_once(
    'src/routes/admin.workspace.tsx',
    '  { to: "/admin/quiz-reviews", label: "Quiz و نظرات" },\n',
    '  { to: "/admin/quiz-reviews", label: "Quiz و نظرات" },\n  { to: "/admin/cafes", label: "بررسی کافه‌ها" },\n',
)
replace_once(
    'src/routes/panel.index.tsx',
    '          <Link to="/panel/organization"',
    '          <Link to="/panel/wholesale" className="rounded-xl border border-[color:var(--mid)] px-4 py-3 text-sm font-bold text-[color:var(--steam)] hover:border-[color:var(--roast)]">قیمت عمده کافه‌ها</Link>\n          <Link to="/panel/organization"',
)

# One-shot generator files are not part of the final source.
(ROOT / '.github/workflows/ps12-ui-bootstrap.yml').unlink()
Path(__file__).unlink()
