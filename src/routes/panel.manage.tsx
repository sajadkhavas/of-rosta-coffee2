import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link, Navigate } from "@tanstack/react-router";
import { useEffect, useMemo, useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton, TextareaField, TextField } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import {
  getSellerProduct,
  listSellerMedia,
  listSellerOrigins,
  sellerProductsQueryOptions,
  sellerRoasteriesQueryOptions,
  updateSellerProduct,
  updateSellerVariant,
  type SellerRoastery,
} from "@/lib/api/seller-operations";
import { mediaUrl, updateSellerRoastery } from "@/lib/api/seller-management";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/panel/manage")({
  head: () => ({
    meta: [
      { title: "ویرایش کاتالوگ روستری | رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: SellerManagementPage,
});

const editableRoles = new Set(["roastery_owner", "roastery_manager", "administrator"]);
const fieldClass = "min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

function errorMessage(error: unknown) {
  return isApiError(error) ? error.message : "ثبت اطلاعات انجام نشد. قرارداد API و اتصال سرویس را بررسی کنید.";
}

function SellerManagementPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "پنل روستری", to: "/panel" }, { label: "ویرایش حرفه‌ای" }]} />
        <AccountGuard>
          {(user) => user.roles.some((role) => editableRoles.has(role)) ? <ManagementDashboard /> : <Navigate to="/forbidden" replace />}
        </AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function ManagementDashboard() {
  const roasteries = useQuery(sellerRoasteriesQueryOptions());
  const [roasteryId, setRoasteryId] = useState("");
  useEffect(() => {
    if (!roasteryId && roasteries.data?.length) setRoasteryId(roasteries.data[0].id);
  }, [roasteries.data, roasteryId]);
  const roastery = roasteries.data?.find((item) => item.id === roasteryId);

  if (roasteries.isLoading) return <Skeleton className="mt-8 h-96" />;
  if (roasteries.isError) return <Alert variant="danger">{errorMessage(roasteries.error)}</Alert>;
  if (!roastery) return <EmptyState title="روستری قابل ویرایشی پیدا نشد" action={<Link to="/panel" className="text-[color:var(--roast)] underline">بازگشت به پنل</Link>} />;

  return (
    <section className="mt-8 space-y-7">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">CATALOG MANAGEMENT</p>
          <h1 className="mt-2 text-3xl font-bold">ویرایش اطلاعات و کاتالوگ</h1>
          <p className="mt-3 max-w-3xl text-sm leading-8 text-[color:var(--light)]">ویرایش اطلاعات حساس روستری یا محصول منتشرشده آن را دوباره به صف بررسی ادمین می‌فرستد. گزینه آسیاب در هیچ بخش وجود ندارد.</p>
        </div>
        <div className="flex flex-wrap gap-3">
          <select value={roasteryId} onChange={(event) => setRoasteryId(event.target.value)} className={fieldClass}>
            {roasteries.data?.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
          <Link to="/panel" className="inline-flex min-h-11 items-center rounded-xl border border-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--roast)]">عملیات روزانه</Link>
        </div>
      </header>
      <RoasteryEditor roastery={roastery} />
      <ProductEditor roastery={roastery} />
    </section>
  );
}

function RoasteryEditor({ roastery }: { roastery: SellerRoastery }) {
  const client = useQueryClient();
  const mediaQuery = useQuery({ queryKey: ["seller", roastery.id, "media"], queryFn: () => listSellerMedia(roastery.id), staleTime: 30_000 });
  const [form, setForm] = useState({
    name: roastery.name,
    slug: roastery.slug,
    city: roastery.city || "",
    description: roastery.description,
    shippingPolicy: roastery.shippingPolicy || "",
    minHours: String(roastery.preparationTime?.minHours ?? 24),
    maxHours: String(roastery.preparationTime?.maxHours ?? 48),
    logoMediaId: roastery.logo?.id || "",
    coverMediaId: roastery.cover?.id || "",
  });
  useEffect(() => setForm({
    name: roastery.name,
    slug: roastery.slug,
    city: roastery.city || "",
    description: roastery.description,
    shippingPolicy: roastery.shippingPolicy || "",
    minHours: String(roastery.preparationTime?.minHours ?? 24),
    maxHours: String(roastery.preparationTime?.maxHours ?? 48),
    logoMediaId: roastery.logo?.id || "",
    coverMediaId: roastery.cover?.id || "",
  }), [roastery]);
  const mutation = useMutation({
    mutationFn: () => updateSellerRoastery(roastery.id, {
      name: form.name,
      slug: form.slug,
      city: form.city,
      description: form.description,
      shippingPolicy: form.shippingPolicy,
      preparationMinHours: Number(form.minHours),
      preparationMaxHours: Number(form.maxHours),
      logoMediaId: form.logoMediaId || null,
      coverMediaId: form.coverMediaId || null,
    }),
    onSuccess: async () => client.invalidateQueries({ queryKey: ["seller", "roasteries"] }),
  });
  const submit = (event: FormEvent) => { event.preventDefault(); mutation.mutate(); };
  const media = mediaQuery.data?.items ?? [];

  return (
    <form onSubmit={submit} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-xl font-bold">اطلاعات روستری</h2><p className="mt-1 text-xs text-[color:var(--light)]">وضعیت فعلی: {roastery.status}</p></div><Button type="submit" loading={mutation.isPending}>ذخیره روستری</Button></div>
      <div className="mt-5 grid gap-4 md:grid-cols-2">
        <TextField label="نام روستری" required value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} />
        <TextField label="Slug" required dir="ltr" value={form.slug} onChange={(event) => setForm((current) => ({ ...current, slug: event.target.value }))} />
        <TextField label="شهر" value={form.city} onChange={(event) => setForm((current) => ({ ...current, city: event.target.value }))} />
        <div className="grid grid-cols-2 gap-3"><TextField label="حداقل آماده‌سازی" inputMode="numeric" value={form.minHours} onChange={(event) => setForm((current) => ({ ...current, minHours: event.target.value.replace(/\D/g, "") }))} /><TextField label="حداکثر آماده‌سازی" inputMode="numeric" value={form.maxHours} onChange={(event) => setForm((current) => ({ ...current, maxHours: event.target.value.replace(/\D/g, "") }))} /></div>
        <div className="md:col-span-2"><TextareaField label="معرفی روستری" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} /></div>
        <div className="md:col-span-2"><TextareaField label="سیاست ارسال" value={form.shippingPolicy} onChange={(event) => setForm((current) => ({ ...current, shippingPolicy: event.target.value }))} /></div>
        <MediaSelect label="لوگو" value={form.logoMediaId} media={media} onChange={(value) => setForm((current) => ({ ...current, logoMediaId: value }))} />
        <MediaSelect label="کاور" value={form.coverMediaId} media={media} onChange={(value) => setForm((current) => ({ ...current, coverMediaId: value }))} />
      </div>
      {mutation.isError ? <div className="mt-4"><Alert variant="danger">{errorMessage(mutation.error)}</Alert></div> : null}
    </form>
  );
}

function ProductEditor({ roastery }: { roastery: SellerRoastery }) {
  const client = useQueryClient();
  const products = useQuery(sellerProductsQueryOptions(roastery.id));
  const origins = useQuery({ queryKey: ["seller", "origins"], queryFn: listSellerOrigins, staleTime: 60_000 });
  const media = useQuery({ queryKey: ["seller", roastery.id, "media"], queryFn: () => listSellerMedia(roastery.id), staleTime: 30_000 });
  const [productId, setProductId] = useState("");
  useEffect(() => { if (!productId && products.data?.items.length) setProductId(products.data.items[0].id); }, [productId, products.data]);
  const detail = useQuery({ queryKey: ["seller", roastery.id, "products", productId, "detail"], queryFn: () => getSellerProduct(roastery.id, productId), enabled: Boolean(productId), staleTime: 0 });
  const product = detail.data;
  const [form, setForm] = useState({ originId: "", name: "", slug: "", shortDescription: "", description: "", processingMethod: "washed" as "washed" | "natural" | "honey" | "other", roastLevel: "medium" as "light" | "medium" | "dark", arabicaPercentage: "100", tastingNotes: "", brewingSuggestions: "", seoTitle: "", seoDescription: "", primaryMediaId: "", galleryMediaIds: [] as string[] });
  useEffect(() => {
    if (!product) return;
    setForm({
      originId: product.origin.id,
      name: product.name,
      slug: product.slug,
      shortDescription: product.shortDescription || "",
      description: product.description,
      processingMethod: product.processingMethod,
      roastLevel: product.roastLevel,
      arabicaPercentage: String(product.arabicaPercentage),
      tastingNotes: product.tastingNotes.join("، "),
      brewingSuggestions: product.brewingSuggestions.join("\n"),
      seoTitle: product.seo.title || "",
      seoDescription: product.seo.description || "",
      primaryMediaId: product.primaryImage?.id || "",
      galleryMediaIds: product.gallery.map((item) => item.id),
    });
  }, [product]);
  const mutation = useMutation({
    mutationFn: () => updateSellerProduct(roastery.id, productId, {
      originId: form.originId,
      name: form.name,
      slug: form.slug,
      shortDescription: form.shortDescription,
      description: form.description,
      processingMethod: form.processingMethod,
      roastLevel: form.roastLevel,
      arabicaPercentage: Number(form.arabicaPercentage),
      tastingNotes: splitList(form.tastingNotes),
      brewingSuggestions: form.brewingSuggestions.split(/\n+/).map((item) => item.trim()).filter(Boolean),
      seoTitle: form.seoTitle,
      seoDescription: form.seoDescription,
      primaryMediaId: form.primaryMediaId || null,
      galleryMediaIds: form.galleryMediaIds,
    }),
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ["seller", "roasteries", roastery.id, "products"] });
      await client.invalidateQueries({ queryKey: ["seller", roastery.id, "products", productId, "detail"] });
    },
  });
  const submit = (event: FormEvent) => { event.preventDefault(); if (productId) mutation.mutate(); };

  return (
    <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      <header className="flex flex-wrap items-end justify-between gap-3"><div><h2 className="text-xl font-bold">ویرایش محصول، SEO و رسانه</h2><p className="mt-1 text-xs text-[color:var(--light)]">محصول منتشرشده با ویرایش محتوا دوباره Review می‌شود.</p></div><select value={productId} onChange={(event) => setProductId(event.target.value)} className={fieldClass}><option value="">انتخاب محصول</option>{products.data?.items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></header>
      {products.isLoading || detail.isLoading ? <Skeleton className="mt-5 h-96" /> : products.isError || detail.isError ? <div className="mt-5"><Alert variant="danger">{errorMessage(products.error || detail.error)}</Alert></div> : !product ? <div className="mt-5"><EmptyState title="محصولی انتخاب نشده" /></div> : (
        <form onSubmit={submit} className="mt-5 space-y-5">
          <div className="grid gap-4 md:grid-cols-2">
            <label className="grid gap-2 text-sm font-bold">خاستگاه<select value={form.originId} onChange={(event) => setForm((current) => ({ ...current, originId: event.target.value }))} className={fieldClass}>{origins.data?.items.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
            <TextField label="نام محصول" required value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} />
            <TextField label="Slug" required dir="ltr" value={form.slug} onChange={(event) => setForm((current) => ({ ...current, slug: event.target.value }))} />
            <TextField label="درصد عربیکا" inputMode="numeric" value={form.arabicaPercentage} onChange={(event) => setForm((current) => ({ ...current, arabicaPercentage: event.target.value.replace(/\D/g, "").slice(0, 3) }))} />
            <label className="grid gap-2 text-sm font-bold">فرآوری<select value={form.processingMethod} onChange={(event) => setForm((current) => ({ ...current, processingMethod: event.target.value as typeof current.processingMethod }))} className={fieldClass}><option value="washed">شسته</option><option value="natural">طبیعی</option><option value="honey">هانی</option><option value="other">سایر</option></select></label>
            <label className="grid gap-2 text-sm font-bold">درجه رست<select value={form.roastLevel} onChange={(event) => setForm((current) => ({ ...current, roastLevel: event.target.value as typeof current.roastLevel }))} className={fieldClass}><option value="light">روشن</option><option value="medium">متوسط</option><option value="dark">تیره</option></select></label>
            <div className="md:col-span-2"><TextField label="یادداشت‌های طعمی" value={form.tastingNotes} onChange={(event) => setForm((current) => ({ ...current, tastingNotes: event.target.value }))} /></div>
            <div className="md:col-span-2"><TextareaField label="توضیح کوتاه" value={form.shortDescription} onChange={(event) => setForm((current) => ({ ...current, shortDescription: event.target.value }))} /></div>
            <div className="md:col-span-2"><TextareaField label="توضیحات کامل" value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} /></div>
            <div className="md:col-span-2"><TextareaField label="پیشنهادهای دم‌آوری؛ هر مورد یک خط" value={form.brewingSuggestions} onChange={(event) => setForm((current) => ({ ...current, brewingSuggestions: event.target.value }))} /></div>
            <TextField label="SEO title" value={form.seoTitle} onChange={(event) => setForm((current) => ({ ...current, seoTitle: event.target.value }))} />
            <TextareaField label="SEO description" value={form.seoDescription} onChange={(event) => setForm((current) => ({ ...current, seoDescription: event.target.value }))} />
            <MediaSelect label="تصویر اصلی" value={form.primaryMediaId} media={media.data?.items ?? []} onChange={(value) => setForm((current) => ({ ...current, primaryMediaId: value }))} />
            <GallerySelect media={media.data?.items ?? []} selected={form.galleryMediaIds} onChange={(galleryMediaIds) => setForm((current) => ({ ...current, galleryMediaIds }))} />
          </div>
          {mutation.isError ? <Alert variant="danger">{errorMessage(mutation.error)}</Alert> : null}
          <Button type="submit" loading={mutation.isPending}>ذخیره محصول</Button>
          <VariantEditors roasteryId={roastery.id} productId={product.id} variants={product.variants} onSaved={async () => { await client.invalidateQueries({ queryKey: ["seller", "roasteries", roastery.id, "products"] }); await detail.refetch(); }} />
        </form>
      )}
    </section>
  );
}

function VariantEditors({ roasteryId, productId, variants, onSaved }: { roasteryId: string; productId: string; variants: Array<{ id: string; sku: string; weightGrams: number; price: number; compareAtPrice: number | null; isAvailable: boolean }>; onSaved: () => Promise<unknown> }) {
  const [drafts, setDrafts] = useState<Record<string, { sku: string; price: string; compareAtPrice: string; isActive: boolean }>>({});
  useEffect(() => setDrafts(Object.fromEntries(variants.map((item) => [item.id, { sku: item.sku, price: String(item.price), compareAtPrice: item.compareAtPrice ? String(item.compareAtPrice) : "", isActive: item.isAvailable }]))), [variants]);
  const mutation = useMutation({ mutationFn: ({ id, value }: { id: string; value: { sku: string; price: string; compareAtPrice: string; isActive: boolean } }) => updateSellerVariant(roasteryId, productId, id, { sku: value.sku, price: Number(value.price), compareAtPrice: value.compareAtPrice ? Number(value.compareAtPrice) : null, isActive: value.isActive }), onSuccess: onSaved });
  return <div className="border-t border-[color:var(--mid)] pt-5"><h3 className="font-bold">ویرایش Variantهای دانه کامل</h3><div className="mt-4 grid gap-3">{variants.map((variant) => { const value = drafts[variant.id]; if (!value) return null; return <div key={variant.id} className="grid gap-3 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 md:grid-cols-[auto_1fr_1fr_1fr_auto]"><span className="self-center text-sm font-bold">{toFa(variant.weightGrams)} گرم</span><input dir="ltr" value={value.sku} onChange={(event) => setDrafts((current) => ({ ...current, [variant.id]: { ...value, sku: event.target.value } }))} className={fieldClass} /><input inputMode="numeric" value={value.price} onChange={(event) => setDrafts((current) => ({ ...current, [variant.id]: { ...value, price: event.target.value.replace(/\D/g, "") } }))} className={fieldClass} /><input inputMode="numeric" value={value.compareAtPrice} onChange={(event) => setDrafts((current) => ({ ...current, [variant.id]: { ...value, compareAtPrice: event.target.value.replace(/\D/g, "") } }))} className={fieldClass} /><Button type="button" variant="outline" loading={mutation.isPending} onClick={() => mutation.mutate({ id: variant.id, value })}>ذخیره</Button></div>; })}</div>{mutation.isError ? <div className="mt-3"><Alert variant="danger">{errorMessage(mutation.error)}</Alert></div> : null}</div>;
}

function MediaSelect({ label, value, media, onChange }: { label: string; value: string; media: Array<{ id: string; alt: string; sources: Array<{ url: string }> }>; onChange: (value: string) => void }) {
  return <label className="grid gap-2 text-sm font-bold">{label}<select value={value} onChange={(event) => onChange(event.target.value)} className={fieldClass}><option value="">بدون تصویر</option>{media.map((item) => <option key={item.id} value={item.id}>{item.alt || item.id}</option>)}</select>{value ? <img src={mediaUrl(media.find((item) => item.id === value)) || ""} alt="پیش‌نمایش" className="h-24 rounded-xl object-cover" /> : null}</label>;
}

function GallerySelect({ media, selected, onChange }: { media: Array<{ id: string; alt: string }>; selected: string[]; onChange: (value: string[]) => void }) {
  const set = useMemo(() => new Set(selected), [selected]);
  return <fieldset className="rounded-xl border border-[color:var(--mid)] p-3"><legend className="px-2 text-sm font-bold">Gallery</legend><div className="max-h-44 space-y-2 overflow-y-auto">{media.map((item) => <label key={item.id} className="flex items-center gap-2 text-xs"><input type="checkbox" checked={set.has(item.id)} onChange={(event) => onChange(event.target.checked ? [...selected, item.id].slice(0, 30) : selected.filter((id) => id !== item.id))} />{item.alt || item.id}</label>)}</div></fieldset>;
}

function splitList(value: string) {
  return [...new Set(value.split(/[،,]/).map((item) => item.trim()).filter(Boolean))].slice(0, 30);
}
