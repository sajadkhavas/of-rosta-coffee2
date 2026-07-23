import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Check, Copy } from "lucide-react";
import { useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, Skeleton } from "@/components/system";
import { createVerifiedReview } from "@/lib/api/reviews";
import { orderQueryOptions } from "@/lib/api/orders";
import { isApiError } from "@/lib/api/client";
import type { OrderLine, OrderStatus, SubOrderStatus } from "@/lib/api/contracts";
import { formatAccountDate, orderStatusLabels, statusBadgeClass, subOrderStatusLabels } from "@/lib/account-format";
import { formatIrr, formatWeight } from "@/lib/catalog-format";
import { absoluteUrl } from "@/config/site";

export const Route = createFileRoute("/orders/$id")({
  head: ({ params }) => ({
    meta: [{ title: `سفارش #${params.id} | رستا` }, { name: "robots", content: "noindex,nofollow" }],
    links: [{ rel: "canonical", href: absoluteUrl(`/orders/${params.id}`) }],
  }),
  component: OrderDetailPage,
});

const timelineSteps = ["ثبت سفارش", "تأیید روستری", "آماده‌سازی", "ارسال", "تحویل"];
function orderStage(status: OrderStatus, subStatuses: SubOrderStatus[]) {
  if (status === "delivered" || status === "partially_delivered") return 4;
  if (status === "shipped" || status === "partially_shipped") return 3;
  if (status === "processing" || subStatuses.some((value) => ["preparing", "ready_to_ship"].includes(value))) return 2;
  if (status === "paid" || subStatuses.some((value) => ["accepted", "preparing", "ready_to_ship", "shipped", "delivered"].includes(value))) return 1;
  return 0;
}

function OrderDetailPage() {
  return <><Navbar /><main className="mx-auto max-w-5xl px-4 py-8"><AccountGuard>{() => <OrderContent />}</AccountGuard></main><Footer /></>;
}

function OrderContent() {
  const { id } = Route.useParams();
  const query = useQuery(orderQueryOptions(id));
  const [copiedCode, setCopiedCode] = useState<string>();
  if (query.isPending) return <div className="grid gap-5"><Skeleton className="h-32" /><Skeleton className="h-44" /><Skeleton className="h-72" /></div>;
  if (query.isError || !query.data) {
    const missing = isApiError(query.error) && query.error.status === 404;
    return <section className="mx-auto max-w-xl py-12 text-center"><h1 className="text-2xl font-bold">{missing ? "سفارش پیدا نشد" : "جزئیات سفارش دریافت نشد"}</h1><p className="mt-3 text-sm text-[color:var(--light)]">{isApiError(query.error) ? query.error.message : "ارتباط با سرویس سفارش‌ها برقرار نشد."}</p><div className="mt-6 flex justify-center gap-3">{!missing ? <Button onClick={() => query.refetch()}>تلاش مجدد</Button> : null}<Link to="/orders" className="inline-flex min-h-11 items-center rounded-xl border border-[color:var(--mid)] px-5 text-sm font-bold">بازگشت</Link></div></section>;
  }

  const order = query.data;
  const stage = orderStage(order.status, order.subOrders.map((item) => item.status));
  const copyTracking = async (code: string) => {
    try { await navigator.clipboard.writeText(code); setCopiedCode(code); window.setTimeout(() => setCopiedCode(undefined), 1500); } catch { setCopiedCode(undefined); }
  };
  const delivered = order.status === "delivered" || order.subOrders.some((item) => item.status === "delivered");

  return (
    <>
      <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "سفارش‌های من", to: "/orders" }, { label: `#${order.orderNumber}` }]} />
      <header className="mt-5 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><div className="flex flex-wrap items-start justify-between gap-4"><div><p className="text-xs font-bold text-[color:var(--roast)]">ORDER</p><h1 className="mt-2 text-2xl font-bold">سفارش #{order.orderNumber}</h1><p className="mt-2 text-xs text-[color:var(--light)]">ثبت: {formatAccountDate(order.placedAt)}</p></div><span className={`rounded-full border px-3 py-1 text-xs font-bold ${statusBadgeClass(order.status)}`}>{orderStatusLabels[order.status]}</span></div></header>

      {!['cancelled','partially_cancelled','refunded'].includes(order.status) ? <section className="mt-5 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"><h2 className="mb-6 font-bold">وضعیت کلی</h2><ol className="grid gap-3 md:grid-cols-5">{timelineSteps.map((label, index) => <li key={label} className="flex items-center gap-3 md:flex-col"><span className={`grid size-9 place-items-center rounded-full border-2 ${index <= stage ? "border-[color:var(--roast)] bg-[color:var(--roast)] text-[color:var(--night)]" : "border-[color:var(--mid)]"}`}>{index < stage ? <Check size={15} /> : (index+1).toLocaleString("fa-IR")}</span><span className={`text-xs ${index === stage ? "font-bold text-[color:var(--roast)]" : "text-[color:var(--light)]"}`}>{label}</span></li>)}</ol></section> : <div className="mt-5"><Alert variant={order.status === "refunded" ? "info" : "warning"}>{orderStatusLabels[order.status]}</Alert></div>}

      <div className="mt-5 grid gap-5 lg:grid-cols-[1fr_19rem]">
        <section className="grid gap-5">
          {order.subOrders.map((subOrder) => <article key={subOrder.id} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
            <div className="flex flex-wrap items-start justify-between gap-4"><div><Link to="/roasteries/$slug" params={{ slug: subOrder.roastery.slug }} className="font-bold">{subOrder.roastery.name}</Link><p className="mt-1 text-xs text-[color:var(--light)]">زیرسفارش #{subOrder.id}</p></div><span className={`rounded-full border px-3 py-1 text-[11px] font-bold ${statusBadgeClass(subOrder.status)}`}>{subOrderStatusLabels[subOrder.status]}</span></div>
            <ul className="mt-5 divide-y divide-[color:var(--mid)]">{subOrder.items.map((item) => <li key={item.id} className="py-4 first:pt-0"><div className="flex gap-4"><div className="size-16 shrink-0 overflow-hidden rounded-xl bg-[color:var(--night)]">{item.product.imageUrl ? <img src={item.product.imageUrl} alt={item.product.name} className="h-full w-full object-cover" /> : null}</div><div className="min-w-0 flex-1"><Link to="/products/$slug" params={{ slug: item.product.slug }} className="font-bold">{item.product.name}</Link><p className="mt-1 text-xs text-[color:var(--light)]">{formatWeight(item.variant.weightGrams)} · دانه کامل · {item.quantity.toLocaleString("fa-IR")} عدد</p><p className="mt-2 font-mono text-sm font-bold text-[color:var(--roast)]">{formatIrr(item.lineTotal)}</p></div></div>{subOrder.status === "delivered" ? <ReviewForm item={item} /> : null}</li>)}</ul>
            {subOrder.shipment ? <div className="mt-5 rounded-xl border border-[color:var(--roast)]/40 bg-[color:var(--night)] p-4"><div className="flex flex-wrap items-center justify-between gap-4"><div><p className="text-xs text-[color:var(--light)]">ارسال {subOrder.shipment.carrier ? `با ${subOrder.shipment.carrier}` : "سفارش"}</p>{subOrder.shipment.trackingCode ? <p dir="ltr" className="mt-1 font-mono text-sm font-bold text-[color:var(--roast)]">{subOrder.shipment.trackingCode}</p> : null}</div>{subOrder.shipment.trackingCode ? <button type="button" onClick={() => copyTracking(subOrder.shipment!.trackingCode!)} className="inline-flex min-h-10 items-center gap-2 rounded-xl bg-[color:var(--roast)] px-4 text-xs font-bold text-[color:var(--night)]"><Copy size={14} />{copiedCode === subOrder.shipment.trackingCode ? "کپی شد" : "کپی کد"}</button> : null}</div></div> : null}
            <dl className="mt-5 space-y-2 border-t border-[color:var(--mid)] pt-4 text-sm"><div className="flex justify-between text-[color:var(--light)]"><dt>جمع اقلام</dt><dd>{formatIrr(subOrder.subtotal)}</dd></div><div className="flex justify-between text-[color:var(--light)]"><dt>ارسال</dt><dd>{formatIrr(subOrder.shippingTotal)}</dd></div></dl>
          </article>)}
        </section>
        <aside className="h-fit rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 lg:sticky lg:top-24"><h2 className="font-bold">خلاصه پرداخت</h2><dl className="mt-4 space-y-3 text-sm"><div className="flex justify-between text-[color:var(--light)]"><dt>جمع محصولات</dt><dd>{formatIrr(order.subtotal)}</dd></div><div className="flex justify-between text-[color:var(--light)]"><dt>ارسال</dt><dd>{formatIrr(order.shippingTotal)}</dd></div><div className="flex justify-between text-[color:var(--light)]"><dt>تخفیف</dt><dd>- {formatIrr(order.discountTotal)}</dd></div><div className="flex justify-between border-t border-[color:var(--mid)] pt-3 font-bold"><dt>مبلغ نهایی</dt><dd className="text-[color:var(--roast)]">{formatIrr(order.grandTotal)}</dd></div></dl>{delivered ? <p className="mt-5 text-xs leading-6 text-[color:var(--muted-gold)]">برای هر آیتم تحویل‌شده یک نظر خرید تأییدشده می‌توان ثبت کرد. نظر ابتدا Pending است.</p> : null}</aside>
      </div>
    </>
  );
}

function ReviewForm({ item }: { item: OrderLine }) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [rating, setRating] = useState(5);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const mutation = useMutation({
    mutationFn: () => createVerifiedReview({ orderItemId: item.id, rating, title, body }),
    onSuccess: async () => {
      setBody(""); setTitle(""); setOpen(false);
      await queryClient.invalidateQueries({ queryKey: ["public", "products", item.product.slug, "reviews"] });
    },
  });
  const submit = (event: FormEvent) => { event.preventDefault(); if (body.trim().length >= 10) mutation.mutate(); };
  if (mutation.isSuccess) return <Alert variant="success" title="نظر ثبت شد">نظر خرید تأییدشده در انتظار Moderation است.</Alert>;
  return <div className="mt-4 border-t border-[color:var(--mid)] pt-4">{!open ? <Button type="button" variant="outline" onClick={() => setOpen(true)}>ثبت نظر برای این محصول</Button> : <form onSubmit={submit} className="space-y-3"><label className="grid gap-2 text-xs font-bold">امتیاز<select value={rating} onChange={(event) => setRating(Number(event.target.value))} className="min-h-10 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3">{[5,4,3,2,1].map((value) => <option key={value} value={value}>{value} ستاره</option>)}</select></label><input value={title} onChange={(event) => setTitle(event.target.value)} maxLength={240} placeholder="عنوان کوتاه (اختیاری)" className="min-h-10 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm" /><textarea required minLength={10} maxLength={10000} value={body} onChange={(event) => setBody(event.target.value)} placeholder="تجربه واقعی خود را بنویسید" className="min-h-28 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3 text-sm" />{mutation.isError ? <Alert variant="danger">{isApiError(mutation.error) ? mutation.error.message : "ثبت نظر انجام نشد."}</Alert> : null}<div className="flex gap-2"><Button type="submit" loading={mutation.isPending}>ثبت نظر</Button><Button type="button" variant="ghost" onClick={() => setOpen(false)}>انصراف</Button></div></form>}</div>;
}
