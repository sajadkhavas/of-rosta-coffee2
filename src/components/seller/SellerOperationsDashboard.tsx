import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertTriangle,
  Boxes,
  CheckCircle2,
  Coffee,
  ImagePlus,
  PackageCheck,
  RefreshCw,
  Send,
  Store,
  Truck,
  WalletCards,
} from "lucide-react";
import { useEffect, useMemo, useState, type FormEvent } from "react";
import {
  Alert,
  Button,
  EmptyState,
  PageHeader,
  Skeleton,
  TextareaField,
  TextField,
  useToast,
} from "@/components/system";
import type { AuthUser, OrderSummary, ProductSummary, ProductVariant } from "@/lib/api/contracts";
import { isApiError } from "@/lib/api/client";
import {
  adjustSellerStock,
  createSellerProduct,
  createSellerRoastBatch,
  createSellerVariant,
  listSellerMedia,
  listSellerOrigins,
  listSellerRoastBatches,
  reportSellerFulfillmentIncident,
  retrySellerMedia,
  RetryableMediaProcessingError,
  sellerOrdersQueryOptions,
  sellerProductsQueryOptions,
  sellerRoasteriesQueryOptions,
  sellerSettlementsQueryOptions,
  transitionSellerOrder,
  updateSellerProduct,
  uploadSellerMedia,
  type FulfillmentInput,
  type ReportFulfillmentIncidentInput,
  type SellerRoastery,
  type StockReason,
} from "@/lib/api/seller-operations";
import { createSellerRoastery } from "@/lib/api/seller-onboarding";
import { listAuthoritativeStockLedger } from "@/lib/api/seller-stock-ledger";
import { bestMediaUrl, mediaSrcSet } from "@/lib/catalog-format";
import { toFa } from "@/lib/persian";

const sellerRoles = new Set([
  "roastery_owner",
  "roastery_manager",
  "roastery_staff",
  "administrator",
]);

const tabs = [
  { id: "orders", label: "سفارش‌ها", icon: PackageCheck },
  { id: "catalog", label: "کاتالوگ و موجودی", icon: Boxes },
  { id: "media", label: "رسانه‌ها", icon: ImagePlus },
  { id: "settlements", label: "تسویه‌ها", icon: WalletCards },
] as const;

type TabId = (typeof tabs)[number]["id"];

type EditableRole = "roastery_owner" | "roastery_manager" | "administrator";
const editableRoles = new Set<EditableRole>([
  "roastery_owner",
  "roastery_manager",
  "administrator",
]);

const fieldClass =
  "min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

export function SellerOperationsDashboard({ user }: { user: AuthUser }) {
  const hasSellerRole = user.roles.some((role) => sellerRoles.has(role));
  const [onboarded, setOnboarded] = useState(false);
  const roasteriesQuery = useQuery({
    ...sellerRoasteriesQueryOptions(),
    enabled: hasSellerRole || onboarded,
  });
  const [selectedRoasteryId, setSelectedRoasteryId] = useState("");
  const [activeTab, setActiveTab] = useState<TabId>("orders");

  useEffect(() => {
    if (!selectedRoasteryId && roasteriesQuery.data?.length) {
      setSelectedRoasteryId(roasteriesQuery.data[0].id);
    }
  }, [roasteriesQuery.data, selectedRoasteryId]);

  const selectedRoastery = roasteriesQuery.data?.find((item) => item.id === selectedRoasteryId);

  if (!hasSellerRole && !onboarded) {
    return <RoasteryOnboarding onCreated={() => setOnboarded(true)} />;
  }

  if (roasteriesQuery.isLoading) {
    return (
      <section className="mt-8 grid gap-4">
        <Skeleton className="h-28" />
        <Skeleton className="h-14" />
        <Skeleton className="h-80" />
      </section>
    );
  }

  if (roasteriesQuery.isError) {
    return (
      <section className="mt-8">
        <Alert variant="danger" title="روستری‌های قابل‌دسترسی دریافت نشد">
          {errorMessage(roasteriesQuery.error)}
        </Alert>
      </section>
    );
  }

  if (!roasteriesQuery.data?.length) {
    return <RoasteryOnboarding onCreated={() => setOnboarded(true)} />;
  }

  if (!selectedRoastery) return null;

  return (
    <section className="mt-8 space-y-7">
      <PageHeader
        eyebrow="ROASTERY OPERATIONS"
        title="پنل عملیات روستری"
        description="محصول، وزن دانه کامل، بچ رست، موجودی، رسانه و سفارش‌ها همگی از قرارداد authoritative لاراول مدیریت می‌شوند."
        actions={
          <div className="flex flex-wrap items-end gap-3">
            <label className="grid gap-1 text-xs font-bold">
              روستری فعال
              <select
                value={selectedRoasteryId}
                onChange={(event) => setSelectedRoasteryId(event.target.value)}
                className={fieldClass}
              >
                {roasteriesQuery.data.map((roastery) => (
                  <option key={roastery.id} value={roastery.id}>
                    {roastery.name}
                  </option>
                ))}
              </select>
            </label>
            <Button
              variant="outline"
              onClick={() => void roasteriesQuery.refetch()}
              loading={roasteriesQuery.isFetching}
            >
              <RefreshCw size={16} />
              تازه‌سازی
            </Button>
          </div>
        }
      />

      <RoasterySummaryCard roastery={selectedRoastery} />

      <nav
        aria-label="بخش‌های پنل روستری"
        className="flex gap-2 overflow-x-auto rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-2"
      >
        {tabs.map((tab) => {
          const Icon = tab.icon;
          const active = activeTab === tab.id;
          return (
            <button
              type="button"
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              aria-current={active ? "page" : undefined}
              className={`inline-flex min-h-11 items-center gap-2 whitespace-nowrap rounded-xl px-4 text-sm font-bold transition ${
                active
                  ? "bg-[color:var(--roast)] text-[color:var(--night)]"
                  : "text-[color:var(--light)] hover:bg-[color:var(--night)] hover:text-[color:var(--steam)]"
              }`}
            >
              <Icon size={17} />
              {tab.label}
            </button>
          );
        })}
      </nav>

      {activeTab === "orders" ? <SellerOrdersWorkspace roastery={selectedRoastery} /> : null}
      {activeTab === "catalog" ? <SellerCatalogWorkspace roastery={selectedRoastery} /> : null}
      {activeTab === "media" ? <SellerMediaWorkspace roastery={selectedRoastery} /> : null}
      {activeTab === "settlements" ? (
        <SellerSettlementsWorkspace roastery={selectedRoastery} />
      ) : null}
    </section>
  );
}

function RoasteryOnboarding({ onCreated }: { onCreated: () => void }) {
  const queryClient = useQueryClient();
  const { pushToast } = useToast();
  const [form, setForm] = useState({
    name: "",
    slug: "",
    city: "",
    description: "",
    shippingPolicy: "",
    minHours: "24",
    maxHours: "48",
  });
  const mutation = useMutation({
    mutationFn: createSellerRoastery,
    onSuccess: async () => {
      await queryClient.invalidateQueries();
      onCreated();
      pushToast({
        title: "درخواست روستری ثبت شد",
        description: "روستری در وضعیت بررسی ادمین ایجاد شد.",
        variant: "success",
      });
    },
  });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    mutation.mutate({
      name: form.name,
      slug: form.slug,
      city: form.city,
      description: form.description,
      shippingPolicy: form.shippingPolicy,
      preparationMinHours: numberOrNull(form.minHours),
      preparationMaxHours: numberOrNull(form.maxHours),
    });
  };

  return (
    <section className="mt-8 mx-auto max-w-3xl rounded-3xl border border-[color:var(--roast)]/40 bg-[color:var(--dark)] p-6 md:p-8">
      <div className="flex items-center gap-3">
        <span className="grid size-12 place-items-center rounded-2xl bg-[color:var(--roast)] text-[color:var(--night)]">
          <Store size={23} />
        </span>
        <div>
          <p className="text-xs font-bold text-[color:var(--roast)]">SELLER ONBOARDING</p>
          <h1 className="mt-1 text-2xl font-bold">ثبت روستری در رستا</h1>
        </div>
      </div>
      <p className="mt-4 text-sm leading-7 text-[color:var(--light)]">
        پس از ثبت، روستری در وضعیت «در انتظار بررسی» قرار می‌گیرد. اطلاعات حساس تأییدشده با ویرایش
        دوباره به صف بررسی بازمی‌گردند.
      </p>
      <form onSubmit={submit} className="mt-6 grid gap-4 md:grid-cols-2">
        <TextField
          label="نام روستری"
          required
          value={form.name}
          onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
        />
        <TextField
          label="Slug"
          required
          dir="ltr"
          value={form.slug}
          onChange={(event) => setForm((current) => ({ ...current, slug: event.target.value }))}
        />
        <TextField
          label="شهر"
          value={form.city}
          onChange={(event) => setForm((current) => ({ ...current, city: event.target.value }))}
        />
        <div className="grid grid-cols-2 gap-3">
          <TextField
            label="حداقل آماده‌سازی"
            inputMode="numeric"
            value={form.minHours}
            onChange={(event) =>
              setForm((current) => ({ ...current, minHours: digits(event.target.value) }))
            }
          />
          <TextField
            label="حداکثر آماده‌سازی"
            inputMode="numeric"
            value={form.maxHours}
            onChange={(event) =>
              setForm((current) => ({ ...current, maxHours: digits(event.target.value) }))
            }
          />
        </div>
        <div className="md:col-span-2">
          <TextareaField
            label="معرفی روستری"
            maxLength={20_000}
            value={form.description}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                description: event.target.value,
              }))
            }
          />
        </div>
        <div className="md:col-span-2">
          <TextareaField
            label="سیاست ارسال"
            maxLength={10_000}
            value={form.shippingPolicy}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                shippingPolicy: event.target.value,
              }))
            }
          />
        </div>
        {mutation.isError ? (
          <div className="md:col-span-2">
            <Alert variant="danger">{errorMessage(mutation.error)}</Alert>
          </div>
        ) : null}
        <div className="md:col-span-2">
          <Button type="submit" className="w-full" loading={mutation.isPending}>
            ثبت درخواست روستری
          </Button>
        </div>
      </form>
    </section>
  );
}

function RoasterySummaryCard({ roastery }: { roastery: SellerRoastery }) {
  const canEdit = roastery.accessRoles.some((role) => editableRoles.has(role as EditableRole));
  return (
    <article className="grid gap-4 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 md:grid-cols-[1fr_auto]">
      <div className="flex items-start gap-4">
        {roastery.logo?.sources[0]?.url ? (
          <img
            src={roastery.logo.sources[0].url}
            alt={roastery.logo.alt}
            className="size-16 rounded-2xl object-cover"
          />
        ) : (
          <span className="grid size-16 place-items-center rounded-2xl bg-[color:var(--night)] text-[color:var(--roast)]">
            <Coffee size={27} />
          </span>
        )}
        <div>
          <h2 className="text-xl font-bold">{roastery.name}</h2>
          <p className="mt-1 text-xs text-[color:var(--light)]">
            {roastery.city || "شهر ثبت نشده"} · {roastery.slug}
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <StatusPill label={roasteryStatusLabel(roastery.status)} />
            {roastery.accessRoles.map((role) => (
              <StatusPill key={role} label={roleLabel(role)} subtle />
            ))}
          </div>
        </div>
      </div>
      <div className="text-xs leading-6 text-[color:var(--light)] md:max-w-64">
        {canEdit
          ? "امکان مدیریت کاتالوگ و اطلاعات روستری برای این نقش فعال است."
          : "این نقش برای مشاهده و عملیات سفارش/موجودی محدود شده است."}
      </div>
    </article>
  );
}

function SellerOrdersWorkspace({ roastery }: { roastery: SellerRoastery }) {
  const queryClient = useQueryClient();
  const { pushToast } = useToast();
  const ordersQuery = useQuery(sellerOrdersQueryOptions(roastery.id));
  const [selectedOrderId, setSelectedOrderId] = useState("");
  const [form, setForm] = useState({
    status: "preparing" as FulfillmentInput["status"],
    carrier: "",
    trackingCode: "",
    internalNote: "",
  });
  const [incidentForm, setIncidentForm] = useState<ReportFulfillmentIncidentInput>({
    code: "inventory_mismatch",
    description: "",
    severity: "high",
  });

  useEffect(() => {
    if (!selectedOrderId && ordersQuery.data?.items.length) {
      setSelectedOrderId(ordersQuery.data.items[0].id);
    }
  }, [ordersQuery.data, selectedOrderId]);

  const selectedOrder = ordersQuery.data?.items.find((order) => order.id === selectedOrderId);
  const selectedSubOrder = selectedOrder?.subOrders.find(
    (subOrder) => subOrder.roastery.id === roastery.id,
  );
  const hubServices =
    selectedSubOrder?.items.flatMap((item) =>
      item.services.filter((service) => service.providerType === "rosta_hub"),
    ) ?? [];
  const inboundHubLeg = selectedSubOrder?.shipmentLegs.find(
    (leg) => leg.routeType === "roastery_to_rosta_hub",
  );
  const allowedActions = fulfillmentActions(selectedSubOrder?.status);

  useEffect(() => {
    if (allowedActions.length && !allowedActions.includes(form.status)) {
      setForm((current) => ({ ...current, status: allowedActions[0] }));
    }
  }, [allowedActions, form.status]);

  const mutation = useMutation({
    mutationFn: (input: FulfillmentInput) =>
      transitionSellerOrder(roastery.id, selectedOrderId, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["seller", "roasteries", roastery.id, "orders"],
      });
      setForm({
        status: "preparing",
        carrier: "",
        trackingCode: "",
        internalNote: "",
      });
      pushToast({ title: "وضعیت سفارش به‌روزرسانی شد", variant: "success" });
    },
  });

  const openIncident = selectedSubOrder?.incidents.find((incident) => incident.status === "open");
  const canReportIncident = ["accepted", "preparing", "ready_to_ship"].includes(
    selectedSubOrder?.status ?? "",
  );
  const incidentMutation = useMutation({
    mutationFn: (input: ReportFulfillmentIncidentInput) =>
      reportSellerFulfillmentIncident(roastery.id, selectedOrderId, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["seller", "roasteries", roastery.id, "orders"],
      });
      setIncidentForm({ code: "inventory_mismatch", description: "", severity: "high" });
      pushToast({
        title: "Incident برای بررسی تیم رستا ثبت شد",
        variant: "success",
      });
    },
  });

  const submitIncident = (event: FormEvent) => {
    event.preventDefault();
    if (!selectedOrderId || !canReportIncident || openIncident) return;
    incidentMutation.mutate(incidentForm);
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!selectedOrderId || !allowedActions.includes(form.status)) return;
    mutation.mutate({
      status: form.status,
      carrier: form.carrier,
      trackingCode: form.trackingCode,
      internalNote: form.internalNote,
    });
  };

  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
      <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
        <div className="flex items-center justify-between gap-3">
          <div>
            <h2 className="font-bold">صف سفارش‌های روستری</h2>
            <p className="mt-1 text-xs text-[color:var(--light)]">
              {toFa(ordersQuery.data?.meta?.total ?? ordersQuery.data?.items.length ?? 0)} سفارش
            </p>
          </div>
          <Button
            variant="outline"
            onClick={() => void ordersQuery.refetch()}
            loading={ordersQuery.isFetching}
          >
            <RefreshCw size={15} />
          </Button>
        </div>
        <div className="mt-5 grid gap-3">
          {ordersQuery.isLoading ? (
            Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} className="h-28" />)
          ) : ordersQuery.isError ? (
            <Alert variant="danger">{errorMessage(ordersQuery.error)}</Alert>
          ) : ordersQuery.data?.items.length ? (
            ordersQuery.data.items.map((order) => (
              <OrderCard
                key={order.id}
                order={order}
                selected={order.id === selectedOrderId}
                onSelect={() => setSelectedOrderId(order.id)}
              />
            ))
          ) : (
            <EmptyState
              title="سفارشی در صف نیست"
              description="سفارش‌های پرداخت‌شده و متعلق به همین روستری اینجا نمایش داده می‌شوند."
            />
          )}
        </div>
      </section>

      <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
        <h2 className="font-bold">اقدام عملیاتی</h2>
        {!selectedOrder || !selectedSubOrder ? (
          <div className="mt-5">
            <EmptyState
              title="سفارشی انتخاب نشده"
              description="برای مشاهده مرحله بعد، یک سفارش را انتخاب کنید."
            />
          </div>
        ) : allowedActions.length === 0 ? (
          <div className="mt-5">
            <Alert variant="info" title="اقدام بعدی برای فروشنده وجود ندارد">
              وضعیت فعلی: {subOrderStatusLabel(selectedSubOrder.status)}
            </Alert>
          </div>
        ) : (
          <form onSubmit={submit} className="mt-5 grid gap-4">
            <label className="grid gap-2 text-sm font-bold">
              مرحله بعد
              <select
                value={form.status}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    status: event.target.value as FulfillmentInput["status"],
                  }))
                }
                className={fieldClass}
              >
                {allowedActions.map((status) => (
                  <option key={status} value={status}>
                    {fulfillmentActionLabel(status)}
                  </option>
                ))}
              </select>
            </label>
            {form.status === "shipped" ? (
              <>
                <TextField
                  label="شرکت حمل"
                  required
                  value={form.carrier}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      carrier: event.target.value,
                    }))
                  }
                />
                <TextField
                  label="کد رهگیری"
                  required
                  dir="ltr"
                  value={form.trackingCode}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      trackingCode: event.target.value,
                    }))
                  }
                />
              </>
            ) : null}
            <TextareaField
              label="یادداشت داخلی اختیاری"
              value={form.internalNote}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  internalNote: event.target.value,
                }))
              }
            />
            {mutation.isError ? (
              <Alert variant="danger">{errorMessage(mutation.error)}</Alert>
            ) : null}
            <Button type="submit" loading={mutation.isPending}>
              {form.status === "shipped" ? <Truck size={16} /> : <Send size={16} />}
              ثبت مرحله
            </Button>
          </form>
        )}

        {selectedSubOrder ? (
          <div className="mt-6 border-t border-[color:var(--mid)] pt-5">
            <Alert variant="info" title="پذیرش قراردادی خودکار">
              سفارش پس از پرداخت قطعی شده است؛ نیازی به پذیرش دستی نیست و روستری موظف به آماده‌سازی
              و تحویل به حمل است.
            </Alert>
            <div className="mt-3 grid gap-2 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 text-xs leading-6 text-[color:var(--light)]">
              <p>مهلت آماده‌سازی: {formatDate(selectedSubOrder.fulfillment.preparationDueAt)}</p>
              <p>مهلت تحویل به حمل: {formatDate(selectedSubOrder.fulfillment.handoffDueAt)}</p>
              <p>وضعیت SLA: {selectedSubOrder.fulfillment.isBreached ? "نقض‌شده" : "در جریان"}</p>
            </div>

            {hubServices.length ? (
              <div
                data-testid="seller-hub-handoff-status"
                className="mt-4 grid gap-2 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 text-xs leading-6 text-[color:var(--light)]"
              >
                <p className="font-bold text-[color:var(--steam)]">تحویل به هاب رستا</p>
                <p>
                  وضعیت مسیر ورودی:{" "}
                  {inboundHubLeg
                    ? shipmentLegStatusLabel(inboundHubLeg.status)
                    : "مسیر ورودی هنوز ایجاد نشده"}
                </p>
                {hubServices.map((service) => (
                  <p key={service.id}>
                    وضعیت دریافت هاب: {service.hubOperation?.label ?? "در انتظار دریافت"}
                    {service.hubOperation?.receivedAt
                      ? ` · ${formatDate(service.hubOperation.receivedAt)}`
                      : ""}
                  </p>
                ))}
              </div>
            ) : null}

            {openIncident ? (
              <div className="mt-4">
                <Alert variant="warning" title="Incident باز است">
                  تغییر مرحله تا تعیین تکلیف تیم رستا متوقف شده است. کد مشکل: {openIncident.code}
                </Alert>
              </div>
            ) : canReportIncident ? (
              <form onSubmit={submitIncident} className="mt-4 grid gap-3">
                <div className="flex items-center gap-2 text-sm font-bold text-[color:var(--roast)]">
                  <AlertTriangle size={17} />
                  اعلام عدم امکان ارسال در شرایط استثنایی
                </div>
                <label className="grid gap-2 text-sm font-bold">
                  نوع مشکل
                  <select
                    value={incidentForm.code}
                    onChange={(event) =>
                      setIncidentForm((current) => ({
                        ...current,
                        code: event.target.value as ReportFulfillmentIncidentInput["code"],
                      }))
                    }
                    className={fieldClass}
                  >
                    <option value="inventory_mismatch">مغایرت موجودی</option>
                    <option value="equipment_failure">خرابی تجهیزات</option>
                    <option value="closure">تعطیلی اضطراری</option>
                    <option value="quality_hold">توقف کنترل کیفیت</option>
                    <option value="carrier_disruption">اختلال حمل</option>
                    <option value="other">سایر</option>
                  </select>
                </label>
                <label className="grid gap-2 text-sm font-bold">
                  شدت
                  <select
                    value={incidentForm.severity}
                    onChange={(event) =>
                      setIncidentForm((current) => ({
                        ...current,
                        severity: event.target.value as ReportFulfillmentIncidentInput["severity"],
                      }))
                    }
                    className={fieldClass}
                  >
                    <option value="medium">متوسط</option>
                    <option value="high">زیاد</option>
                    <option value="critical">بحرانی</option>
                  </select>
                </label>
                <TextareaField
                  label="شرح دقیق مشکل"
                  required
                  value={incidentForm.description}
                  onChange={(event) =>
                    setIncidentForm((current) => ({
                      ...current,
                      description: event.target.value,
                    }))
                  }
                />
                {incidentMutation.isError ? (
                  <Alert variant="danger">{errorMessage(incidentMutation.error)}</Alert>
                ) : null}
                <Button type="submit" variant="outline" loading={incidentMutation.isPending}>
                  <AlertTriangle size={16} />
                  ثبت Incident برای پشتیبانی رستا
                </Button>
              </form>
            ) : null}
          </div>
        ) : null}
      </section>
    </div>
  );
}

function OrderCard({
  order,
  selected,
  onSelect,
}: {
  order: OrderSummary;
  selected: boolean;
  onSelect: () => void;
}) {
  const subOrder = order.subOrders[0];
  return (
    <button
      type="button"
      onClick={onSelect}
      className={`rounded-2xl border p-4 text-start transition ${
        selected
          ? "border-[color:var(--roast)] bg-[color:var(--night)]"
          : "border-[color:var(--mid)] bg-[color:var(--night)]/60 hover:border-[color:var(--roast)]/60"
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="font-bold">سفارش {order.orderNumber}</p>
          <p className="mt-1 text-xs text-[color:var(--light)]">
            {toFa(subOrder?.items.length ?? 0)} قلم · {formatIrr(order.grandTotal)}
          </p>
        </div>
        <StatusPill label={subOrder ? subOrderStatusLabel(subOrder.status) : order.status} />
      </div>
      {subOrder?.items.length ? (
        <p className="mt-3 line-clamp-2 text-xs leading-6 text-[color:var(--light)]">
          {subOrder.items
            .map(
              (item) =>
                `${item.product.name}، ${toFa(item.variant.weightGrams)} گرم × ${toFa(item.quantity)}`,
            )
            .join(" · ")}
        </p>
      ) : null}
    </button>
  );
}

function SellerCatalogWorkspace({ roastery }: { roastery: SellerRoastery }) {
  const queryClient = useQueryClient();
  const { pushToast } = useToast();
  const productsQuery = useQuery(sellerProductsQueryOptions(roastery.id));
  const originsQuery = useQuery({
    queryKey: ["seller", "origins"],
    queryFn: listSellerOrigins,
    staleTime: 120_000,
  });
  const [selectedProductId, setSelectedProductId] = useState("");
  const canEditCatalog = roastery.accessRoles.some((role) =>
    editableRoles.has(role as EditableRole),
  );

  useEffect(() => {
    if (!selectedProductId && productsQuery.data?.items.length) {
      setSelectedProductId(productsQuery.data.items[0].id);
    }
  }, [productsQuery.data, selectedProductId]);

  const selectedProduct = productsQuery.data?.items.find(
    (product) => product.id === selectedProductId,
  );

  const refreshProducts = async () => {
    await queryClient.invalidateQueries({
      queryKey: ["seller", "roasteries", roastery.id, "products"],
    });
  };

  const productMutation = useMutation({
    mutationFn: (input: Parameters<typeof createSellerProduct>[1]) =>
      createSellerProduct(roastery.id, input),
    onSuccess: async (product) => {
      await refreshProducts();
      setSelectedProductId(product.id);
      pushToast({ title: "محصول ایجاد شد", variant: "success" });
    },
  });

  const statusMutation = useMutation({
    mutationFn: (status: "draft" | "review" | "archived") =>
      updateSellerProduct(roastery.id, selectedProductId, { status }),
    onSuccess: async () => {
      await refreshProducts();
      pushToast({ title: "وضعیت محصول به‌روزرسانی شد", variant: "success" });
    },
  });

  return (
    <div className="space-y-6">
      {!canEditCatalog ? (
        <Alert variant="info" title="دسترسی مشاهده و عملیات موجودی">
          نقش Staff نمی‌تواند محصول یا Variant ایجاد کند؛ عملیات موجودی و سفارش همچنان طبق Scope
          روستری در دسترس است.
        </Alert>
      ) : null}

      <div className="grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
        <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <div className="flex items-center justify-between gap-3">
            <div>
              <h2 className="font-bold">محصولات روستری</h2>
              <p className="mt-1 text-xs text-[color:var(--light)]">
                {toFa(productsQuery.data?.items.length ?? 0)} محصول
              </p>
            </div>
            <Button
              variant="outline"
              onClick={() => void productsQuery.refetch()}
              loading={productsQuery.isFetching}
            >
              <RefreshCw size={15} />
            </Button>
          </div>
          <div className="mt-5 grid gap-3">
            {productsQuery.isLoading ? (
              Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} className="h-24" />)
            ) : productsQuery.isError ? (
              <Alert variant="danger">{errorMessage(productsQuery.error)}</Alert>
            ) : productsQuery.data?.items.length ? (
              productsQuery.data.items.map((product) => (
                <ProductCard
                  key={product.id}
                  product={product}
                  selected={product.id === selectedProductId}
                  onSelect={() => setSelectedProductId(product.id)}
                />
              ))
            ) : (
              <EmptyState
                title="محصولی ایجاد نشده"
                description="محصول ابتدا Draft است و پس از تکمیل برای Review ارسال می‌شود."
              />
            )}
          </div>
        </section>

        {canEditCatalog ? (
          <CreateProductForm
            origins={originsQuery.data?.items ?? []}
            loadingOrigins={originsQuery.isLoading}
            onSubmit={(input) => productMutation.mutate(input)}
            pending={productMutation.isPending}
            error={productMutation.error}
          />
        ) : (
          <CatalogReadOnlySummary product={selectedProduct} />
        )}
      </div>

      {selectedProduct ? (
        <ProductOperations
          roastery={roastery}
          product={selectedProduct}
          canEditCatalog={canEditCatalog}
          onRefresh={refreshProducts}
          onSetStatus={(status) => statusMutation.mutate(status)}
          statusPending={statusMutation.isPending}
          statusError={statusMutation.error}
        />
      ) : null}
    </div>
  );
}

function ProductCard({
  product,
  selected,
  onSelect,
}: {
  product: ProductSummary;
  selected: boolean;
  onSelect: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={`flex items-center gap-3 rounded-2xl border p-3 text-start transition ${
        selected
          ? "border-[color:var(--roast)] bg-[color:var(--night)]"
          : "border-[color:var(--mid)] bg-[color:var(--night)]/60 hover:border-[color:var(--roast)]/60"
      }`}
    >
      {product.primaryImage?.sources[0]?.url ? (
        <img
          src={product.primaryImage.sources[0].url}
          alt={product.primaryImage.alt}
          className="size-14 rounded-xl object-cover"
        />
      ) : (
        <span className="grid size-14 place-items-center rounded-xl bg-[color:var(--dark)] text-[color:var(--roast)]">
          <Coffee size={21} />
        </span>
      )}
      <div className="min-w-0 flex-1">
        <p className="truncate font-bold">{product.name}</p>
        <p className="mt-1 text-xs text-[color:var(--light)]">
          {product.origin.name} · {toFa(product.variants.length)} وزن
        </p>
      </div>
      <StatusPill label={productStatusLabel(product.status)} />
    </button>
  );
}

function CreateProductForm({
  origins,
  loadingOrigins,
  onSubmit,
  pending,
  error,
}: {
  origins: Array<{ id: string; name: string }>;
  loadingOrigins: boolean;
  onSubmit: (input: Parameters<typeof createSellerProduct>[1]) => void;
  pending: boolean;
  error: unknown;
}) {
  const [form, setForm] = useState({
    originId: "",
    name: "",
    slug: "",
    shortDescription: "",
    description: "",
    processingMethod: "washed" as "washed" | "natural" | "honey" | "other",
    roastLevel: "medium" as "light" | "medium" | "dark",
    arabicaPercentage: "100",
    tastingNotes: "",
    packagingFeeMode: "free" as "free" | "fixed",
    packagingFeeAmount: "0",
  });

  useEffect(() => {
    if (!form.originId && origins.length) {
      setForm((current) => ({ ...current, originId: origins[0].id }));
    }
  }, [form.originId, origins]);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    onSubmit({
      originId: form.originId,
      name: form.name,
      slug: form.slug,
      shortDescription: form.shortDescription,
      description: form.description,
      processingMethod: form.processingMethod,
      roastLevel: form.roastLevel,
      arabicaPercentage: Number(form.arabicaPercentage),
      tastingNotes: commaList(form.tastingNotes),
      packagingFeeMode: form.packagingFeeMode,
      packagingFeeAmount: Number(form.packagingFeeAmount || 0),
      status: "draft",
    });
  };

  return (
    <form
      onSubmit={submit}
      className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
    >
      <h2 className="font-bold">محصول جدید</h2>
      <p className="mt-1 text-xs text-[color:var(--light)]">
        فقط دانه کامل؛ وزن‌ها بعد از ایجاد محصول اضافه می‌شوند.
      </p>
      <div className="mt-5 grid gap-4 md:grid-cols-2">
        <label className="grid gap-2 text-sm font-bold">
          خاستگاه
          <select
            required
            disabled={loadingOrigins}
            value={form.originId}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                originId: event.target.value,
              }))
            }
            className={fieldClass}
          >
            {origins.map((origin) => (
              <option key={origin.id} value={origin.id}>
                {origin.name}
              </option>
            ))}
          </select>
        </label>
        <TextField
          label="نام محصول"
          required
          value={form.name}
          onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
        />
        <TextField
          label="Slug"
          required
          dir="ltr"
          value={form.slug}
          onChange={(event) => setForm((current) => ({ ...current, slug: event.target.value }))}
        />
        <TextField
          label="درصد عربیکا"
          inputMode="numeric"
          value={form.arabicaPercentage}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              arabicaPercentage: digits(event.target.value).slice(0, 3),
            }))
          }
        />
        <label className="grid gap-2 text-sm font-bold">
          فرآوری
          <select
            value={form.processingMethod}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                processingMethod: event.target.value as typeof current.processingMethod,
              }))
            }
            className={fieldClass}
          >
            <option value="washed">شسته</option>
            <option value="natural">طبیعی</option>
            <option value="honey">هانی</option>
            <option value="other">سایر</option>
          </select>
        </label>
        <label className="grid gap-2 text-sm font-bold">
          درجه رست
          <select
            value={form.roastLevel}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                roastLevel: event.target.value as typeof current.roastLevel,
              }))
            }
            className={fieldClass}
          >
            <option value="light">روشن</option>
            <option value="medium">متوسط</option>
            <option value="dark">تیره</option>
          </select>
        </label>
        <label className="grid gap-2 text-sm font-bold">
          هزینه بسته‌بندی
          <select
            value={form.packagingFeeMode}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                packagingFeeMode: event.target.value as typeof current.packagingFeeMode,
                packagingFeeAmount:
                  event.target.value === "free" ? "0" : current.packagingFeeAmount,
              }))
            }
            className={fieldClass}
          >
            <option value="free">رایگان</option>
            <option value="fixed">مبلغ ثابت برای هر بسته</option>
          </select>
        </label>
        <TextField
          label="مبلغ هر بسته (ریال)"
          inputMode="numeric"
          disabled={form.packagingFeeMode === "free"}
          value={form.packagingFeeAmount}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              packagingFeeAmount: digits(event.target.value).slice(0, 16),
            }))
          }
        />
        <div className="md:col-span-2">
          <TextField
            label="یادداشت‌های طعمی؛ جداشده با ویرگول"
            required
            value={form.tastingNotes}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                tastingNotes: event.target.value,
              }))
            }
          />
        </div>
        <div className="md:col-span-2">
          <TextareaField
            label="توضیح کوتاه"
            value={form.shortDescription}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                shortDescription: event.target.value,
              }))
            }
          />
        </div>
        <div className="md:col-span-2">
          <TextareaField
            label="توضیحات کامل"
            value={form.description}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                description: event.target.value,
              }))
            }
          />
        </div>
      </div>
      {error ? (
        <div className="mt-4">
          <Alert variant="danger">{errorMessage(error)}</Alert>
        </div>
      ) : null}
      <Button type="submit" className="mt-5 w-full" loading={pending}>
        ایجاد Draft محصول
      </Button>
    </form>
  );
}

function CatalogReadOnlySummary({ product }: { product?: ProductSummary }) {
  return (
    <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      {product ? (
        <>
          <h2 className="font-bold">{product.name}</h2>
          <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">
            {product.shortDescription || "توضیح کوتاه ثبت نشده است."}
          </p>
        </>
      ) : (
        <EmptyState
          title="محصولی انتخاب نشده"
          description="برای مشاهده اطلاعات کاتالوگ یک محصول را انتخاب کنید."
        />
      )}
    </section>
  );
}

function ProductOperations({
  roastery,
  product,
  canEditCatalog,
  onRefresh,
  onSetStatus,
  statusPending,
  statusError,
}: {
  roastery: SellerRoastery;
  product: ProductSummary;
  canEditCatalog: boolean;
  onRefresh: () => Promise<void>;
  onSetStatus: (status: "draft" | "review" | "archived") => void;
  statusPending: boolean;
  statusError: unknown;
}) {
  const queryClient = useQueryClient();
  const { pushToast } = useToast();
  const [selectedVariantId, setSelectedVariantId] = useState(product.variants[0]?.id ?? "");
  const [packagingMode, setPackagingMode] = useState<"free" | "fixed">(product.packaging.mode);
  const [packagingAmount, setPackagingAmount] = useState(String(product.packaging.feeAmount));

  useEffect(() => {
    if (!product.variants.some((variant) => variant.id === selectedVariantId)) {
      setSelectedVariantId(product.variants[0]?.id ?? "");
    }
  }, [product, selectedVariantId]);

  const batchesQuery = useQuery({
    queryKey: ["seller", roastery.id, product.id, "roast-batches"],
    queryFn: () => listSellerRoastBatches(roastery.id, product.id),
    staleTime: 20_000,
  });
  const ledgerQuery = useQuery({
    queryKey: ["seller", roastery.id, selectedVariantId, "stock-ledger"],
    queryFn: () => listAuthoritativeStockLedger(roastery.id, selectedVariantId),
    enabled: Boolean(selectedVariantId),
    staleTime: 10_000,
  });

  const variantMutation = useMutation({
    mutationFn: (input: Parameters<typeof createSellerVariant>[2]) =>
      createSellerVariant(roastery.id, product.id, input),
    onSuccess: async (variant) => {
      await onRefresh();
      setSelectedVariantId(variant.id);
      pushToast({ title: "وزن دانه کامل اضافه شد", variant: "success" });
    },
  });
  const batchMutation = useMutation({
    mutationFn: (input: Parameters<typeof createSellerRoastBatch>[2]) =>
      createSellerRoastBatch(roastery.id, product.id, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["seller", roastery.id, product.id, "roast-batches"],
      });
      await onRefresh();
      pushToast({ title: "بچ رست ثبت شد", variant: "success" });
    },
  });
  const packagingMutation = useMutation({
    mutationFn: () =>
      updateSellerProduct(roastery.id, product.id, {
        packagingFeeMode: packagingMode,
        packagingFeeAmount: packagingMode === "fixed" ? Number(packagingAmount || 0) : 0,
      }),
    onSuccess: async () => {
      await onRefresh();
      pushToast({ title: "هزینه بسته‌بندی محصول ذخیره شد", variant: "success" });
    },
  });
  const stockMutation = useMutation({
    mutationFn: (input: Parameters<typeof adjustSellerStock>[2]) =>
      adjustSellerStock(roastery.id, selectedVariantId, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["seller", roastery.id, selectedVariantId, "stock-ledger"],
      });
      await onRefresh();
      pushToast({ title: "موجودی در Ledger ثبت شد", variant: "success" });
    },
  });

  return (
    <section className="rounded-2xl border border-[color:var(--roast)]/35 bg-[color:var(--dark)] p-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-bold text-[color:var(--roast)]">PRODUCT OPERATIONS</p>
          <h2 className="mt-2 text-xl font-bold">{product.name}</h2>
          <p className="mt-1 text-xs text-[color:var(--light)]">
            {product.origin.name} · {productStatusLabel(product.status)} ·{" "}
            {product.packaging.isFree
              ? "بسته‌بندی رایگان"
              : `بسته‌بندی ${formatIrr(product.packaging.feeAmount)}`}
          </p>
        </div>
        {canEditCatalog ? (
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => onSetStatus("draft")} loading={statusPending}>
              Draft
            </Button>
            <Button onClick={() => onSetStatus("review")} loading={statusPending}>
              ارسال برای بررسی
            </Button>
            <Button
              variant="outline"
              onClick={() => onSetStatus("archived")}
              loading={statusPending}
            >
              بایگانی
            </Button>
          </div>
        ) : null}
      </div>
      {statusError ? (
        <div className="mt-4">
          <Alert variant="danger">{errorMessage(statusError)}</Alert>
        </div>
      ) : null}

      {canEditCatalog ? (
        <div className="mt-5 grid gap-3 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 md:grid-cols-[1fr_1fr_auto]">
          <label className="grid gap-2 text-sm font-bold">
            بسته‌بندی محصول
            <select
              value={packagingMode}
              onChange={(event) => {
                const mode = event.target.value as "free" | "fixed";
                setPackagingMode(mode);
                if (mode === "free") setPackagingAmount("0");
              }}
              className={fieldClass}
            >
              <option value="free">رایگان</option>
              <option value="fixed">مبلغ ثابت برای هر بسته</option>
            </select>
          </label>
          <TextField
            label="مبلغ هر بسته (ریال)"
            inputMode="numeric"
            disabled={packagingMode === "free"}
            value={packagingAmount}
            onChange={(event) => setPackagingAmount(digits(event.target.value).slice(0, 16))}
          />
          <Button
            type="button"
            className="self-end"
            loading={packagingMutation.isPending}
            onClick={() => packagingMutation.mutate()}
          >
            ذخیره بسته‌بندی
          </Button>
          {packagingMutation.isError ? (
            <div className="md:col-span-3">
              <Alert variant="danger">{errorMessage(packagingMutation.error)}</Alert>
            </div>
          ) : null}
        </div>
      ) : null}

      <div className="mt-6 grid gap-6 xl:grid-cols-3">
        <VariantPanel
          product={product}
          selectedVariantId={selectedVariantId}
          onSelectVariant={setSelectedVariantId}
          canEditCatalog={canEditCatalog}
          onCreate={(input) => variantMutation.mutate(input)}
          pending={variantMutation.isPending}
          error={variantMutation.error}
        />
        <RoastBatchPanel
          items={batchesQuery.data?.items ?? []}
          canCreate={canEditCatalog}
          loading={batchesQuery.isLoading}
          onCreate={(input) => batchMutation.mutate(input)}
          pending={batchMutation.isPending}
          error={batchMutation.error}
        />
        <StockPanel
          variants={product.variants}
          selectedVariantId={selectedVariantId}
          onSelectVariant={setSelectedVariantId}
          batches={batchesQuery.data?.items ?? []}
          ledger={ledgerQuery.data ?? []}
          loading={ledgerQuery.isLoading}
          onAdjust={(input) => stockMutation.mutate(input)}
          pending={stockMutation.isPending}
          error={stockMutation.error}
        />
      </div>
    </section>
  );
}

function VariantPanel({
  product,
  selectedVariantId,
  onSelectVariant,
  canEditCatalog,
  onCreate,
  pending,
  error,
}: {
  product: ProductSummary;
  selectedVariantId: string;
  onSelectVariant: (id: string) => void;
  canEditCatalog: boolean;
  onCreate: (input: Parameters<typeof createSellerVariant>[2]) => void;
  pending: boolean;
  error: unknown;
}) {
  const usedWeights = new Set(product.variants.map((variant) => variant.weightGrams));
  const availableWeights = ([50, 100, 250, 500, 1000] as const).filter(
    (weight) => !usedWeights.has(weight),
  );
  const [form, setForm] = useState({
    sku: "",
    weight: availableWeights[0] ?? 250,
    price: "",
    compareAtPrice: "",
  });

  useEffect(() => {
    if (!availableWeights.includes(form.weight as never) && availableWeights.length) {
      setForm((current) => ({ ...current, weight: availableWeights[0] }));
    }
  }, [availableWeights, form.weight]);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    onCreate({
      sku: form.sku,
      weightGrams: form.weight,
      price: Number(form.price),
      compareAtPrice: form.compareAtPrice ? Number(form.compareAtPrice) : null,
      isActive: true,
    });
  };

  return (
    <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
      <h3 className="font-bold">وزن‌های دانه کامل</h3>
      <div className="mt-4 grid gap-2">
        {product.variants.map((variant) => (
          <button
            type="button"
            key={variant.id}
            onClick={() => onSelectVariant(variant.id)}
            className={`flex items-center justify-between rounded-xl border px-3 py-2 text-sm ${
              selectedVariantId === variant.id
                ? "border-[color:var(--roast)]"
                : "border-[color:var(--mid)]"
            }`}
          >
            <span>{toFa(variant.weightGrams)} گرم</span>
            <span className="font-mono-num text-xs text-[color:var(--roast)]">
              {formatIrr(variant.price)}
            </span>
          </button>
        ))}
      </div>
      {canEditCatalog && availableWeights.length ? (
        <form onSubmit={submit} className="mt-5 grid gap-3 border-t border-[color:var(--mid)] pt-4">
          <TextField
            label="SKU"
            required
            dir="ltr"
            value={form.sku}
            onChange={(event) => setForm((current) => ({ ...current, sku: event.target.value }))}
          />
          <label className="grid gap-2 text-sm font-bold">
            وزن
            <select
              value={form.weight}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  weight: Number(event.target.value) as typeof current.weight,
                }))
              }
              className={fieldClass}
            >
              {availableWeights.map((weight) => (
                <option key={weight} value={weight}>
                  {toFa(weight)} گرم
                </option>
              ))}
            </select>
          </label>
          <TextField
            label="قیمت ریالی"
            required
            inputMode="numeric"
            value={form.price}
            onChange={(event) =>
              setForm((current) => ({ ...current, price: digits(event.target.value) }))
            }
          />
          <TextField
            label="قیمت مقایسه‌ای اختیاری"
            inputMode="numeric"
            value={form.compareAtPrice}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                compareAtPrice: digits(event.target.value),
              }))
            }
          />
          {error ? <Alert variant="danger">{errorMessage(error)}</Alert> : null}
          <Button type="submit" loading={pending}>
            افزودن وزن
          </Button>
        </form>
      ) : null}
    </div>
  );
}

function RoastBatchPanel({
  items,
  canCreate,
  loading,
  onCreate,
  pending,
  error,
}: {
  items: Array<{ id: string; batchCode: string; roastedAt: string }>;
  canCreate: boolean;
  loading: boolean;
  onCreate: (input: Parameters<typeof createSellerRoastBatch>[2]) => void;
  pending: boolean;
  error: unknown;
}) {
  const [form, setForm] = useState({
    batchCode: "",
    roastedAt: "",
    availableFrom: "",
  });
  const submit = (event: FormEvent) => {
    event.preventDefault();
    onCreate({
      batchCode: form.batchCode,
      roastedAt: form.roastedAt,
      availableFrom: form.availableFrom || null,
      isActive: true,
    });
  };
  return (
    <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
      <h3 className="font-bold">بچ‌های رست</h3>
      <div className="mt-4 grid gap-2">
        {loading ? (
          <Skeleton className="h-20" />
        ) : items.length ? (
          items.slice(0, 5).map((batch) => (
            <div key={batch.id} className="rounded-xl border border-[color:var(--mid)] p-3">
              <p className="font-mono-num text-sm font-bold">{batch.batchCode}</p>
              <p className="mt-1 text-[10px] text-[color:var(--light)]">
                {formatDate(batch.roastedAt)}
              </p>
            </div>
          ))
        ) : (
          <p className="text-xs text-[color:var(--light)]">بچ رستی ثبت نشده است.</p>
        )}
      </div>
      {canCreate ? (
        <form onSubmit={submit} className="mt-5 grid gap-3 border-t border-[color:var(--mid)] pt-4">
          <TextField
            label="کد Batch"
            required
            dir="ltr"
            value={form.batchCode}
            onChange={(event) =>
              setForm((current) => ({ ...current, batchCode: event.target.value }))
            }
          />
          <TextField
            label="زمان رست"
            required
            type="datetime-local"
            value={form.roastedAt}
            onChange={(event) =>
              setForm((current) => ({ ...current, roastedAt: event.target.value }))
            }
          />
          <TextField
            label="قابل فروش از"
            type="datetime-local"
            value={form.availableFrom}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                availableFrom: event.target.value,
              }))
            }
          />
          {error ? <Alert variant="danger">{errorMessage(error)}</Alert> : null}
          <Button type="submit" loading={pending}>
            ثبت Batch
          </Button>
        </form>
      ) : null}
    </div>
  );
}

function StockPanel({
  variants,
  selectedVariantId,
  onSelectVariant,
  batches,
  ledger,
  loading,
  onAdjust,
  pending,
  error,
}: {
  variants: ProductVariant[];
  selectedVariantId: string;
  onSelectVariant: (id: string) => void;
  batches: Array<{ id: string; batchCode: string }>;
  ledger: Array<{
    id: string;
    delta: number;
    balance_after: number;
    reason: string;
    created_at?: string | null;
  }>;
  loading: boolean;
  onAdjust: (input: Parameters<typeof adjustSellerStock>[2]) => void;
  pending: boolean;
  error: unknown;
}) {
  const [form, setForm] = useState({
    delta: "",
    reason: "purchase" as Exclude<StockReason, "reservation" | "release" | "sale">,
    roastBatchId: "",
  });
  const submit = (event: FormEvent) => {
    event.preventDefault();
    onAdjust({
      delta: Number(form.delta),
      reason: form.reason,
      roastBatchId: form.roastBatchId || null,
      idempotencyKey: newLedgerIdempotencyKey(),
    });
  };
  return (
    <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
      <h3 className="font-bold">موجودی authoritative</h3>
      <label className="mt-4 grid gap-2 text-sm font-bold">
        وزن فعال
        <select
          value={selectedVariantId}
          onChange={(event) => onSelectVariant(event.target.value)}
          className={fieldClass}
        >
          {variants.map((variant) => (
            <option key={variant.id} value={variant.id}>
              {toFa(variant.weightGrams)} گرم · {variant.sku}
            </option>
          ))}
        </select>
      </label>
      <div className="mt-4 grid gap-2">
        {loading ? (
          <Skeleton className="h-20" />
        ) : ledger.length ? (
          ledger.slice(0, 5).map((entry) => (
            <div
              key={entry.id}
              className="flex items-center justify-between rounded-xl border border-[color:var(--mid)] p-3 text-xs"
            >
              <span>
                {stockReasonLabel(entry.reason)} · {formatDate(entry.created_at)}
              </span>
              <span className="font-mono-num">
                {entry.delta > 0 ? "+" : ""}
                {toFa(entry.delta)} → {toFa(entry.balance_after)}
              </span>
            </div>
          ))
        ) : (
          <p className="text-xs text-[color:var(--light)]">گردش موجودی ثبت نشده است.</p>
        )}
      </div>
      {selectedVariantId ? (
        <form onSubmit={submit} className="mt-5 grid gap-3 border-t border-[color:var(--mid)] pt-4">
          <TextField
            label="تغییر موجودی؛ مثبت یا منفی"
            required
            inputMode="numeric"
            dir="ltr"
            value={form.delta}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                delta: event.target.value.replace(/[^0-9-]/g, ""),
              }))
            }
          />
          <label className="grid gap-2 text-sm font-bold">
            دلیل
            <select
              value={form.reason}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  reason: event.target.value as typeof current.reason,
                }))
              }
              className={fieldClass}
            >
              <option value="opening">موجودی اولیه</option>
              <option value="purchase">ورود خرید/تولید</option>
              <option value="correction">اصلاح شمارش</option>
              <option value="damage">آسیب‌دیده</option>
              <option value="expiry">منقضی‌شده</option>
              <option value="return">مرجوعی</option>
            </select>
          </label>
          <label className="grid gap-2 text-sm font-bold">
            Batch اختیاری
            <select
              value={form.roastBatchId}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  roastBatchId: event.target.value,
                }))
              }
              className={fieldClass}
            >
              <option value="">بدون اتصال</option>
              {batches.map((batch) => (
                <option key={batch.id} value={batch.id}>
                  {batch.batchCode}
                </option>
              ))}
            </select>
          </label>
          {error ? <Alert variant="danger">{errorMessage(error)}</Alert> : null}
          <Button type="submit" loading={pending}>
            ثبت در Ledger
          </Button>
        </form>
      ) : null}
    </div>
  );
}

function SellerMediaWorkspace({ roastery }: { roastery: SellerRoastery }) {
  const queryClient = useQueryClient();
  const { pushToast } = useToast();
  const mediaQuery = useQuery({
    queryKey: ["seller", "roasteries", roastery.id, "media"],
    queryFn: () => listSellerMedia(roastery.id),
    staleTime: 30_000,
  });
  const [file, setFile] = useState<File | null>(null);
  const [alt, setAlt] = useState("");
  const [retryUploadId, setRetryUploadId] = useState<string | null>(null);
  const [uploadStage, setUploadStage] = useState<"idle" | "hashing" | "uploading" | "processing">(
    "idle",
  );
  const mutation = useMutation({
    mutationFn: () => {
      if (retryUploadId) {
        setUploadStage("processing");
        return retrySellerMedia(roastery.id, retryUploadId);
      }
      if (!file) throw new Error("فایل تصویر انتخاب نشده است.");
      return uploadSellerMedia(roastery.id, { file, alt, onProgress: setUploadStage });
    },
    onSuccess: async () => {
      setFile(null);
      setAlt("");
      setUploadStage("idle");
      setRetryUploadId(null);
      await queryClient.invalidateQueries({
        queryKey: ["seller", "roasteries", roastery.id, "media"],
      });
      pushToast({ title: "رسانه با موفقیت ثبت شد", variant: "success" });
    },
    onError: (error) => {
      setUploadStage("idle");
      if (error instanceof RetryableMediaProcessingError) {
        setRetryUploadId(error.uploadId);
      }
    },
  });
  const submit = (event: FormEvent) => {
    event.preventDefault();
    mutation.mutate();
  };
  return (
    <div className="grid gap-6 xl:grid-cols-[minmax(0,0.75fr)_minmax(0,1.25fr)]">
      <form
        onSubmit={submit}
        className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
      >
        <h2 className="font-bold">آپلود امن رسانه</h2>
        <p className="mt-2 text-xs leading-6 text-[color:var(--light)]">
          فایل مستقیم با Signed PUT به Object Storage می‌رود؛ کلید و URL عمومی فقط توسط Backend
          ساخته می‌شوند.
        </p>
        <label className="mt-5 grid gap-2 text-sm font-bold">
          تصویر
          <input
            type="file"
            required
            accept="image/jpeg,image/png,image/webp,image/avif"
            onChange={(event) => {
              setFile(event.target.files?.[0] ?? null);
              setRetryUploadId(null);
            }}
            className={fieldClass}
          />
        </label>
        <div className="mt-4">
          <TextField
            label="متن جایگزین تصویر"
            required
            maxLength={300}
            value={alt}
            onChange={(event) => setAlt(event.target.value)}
          />
        </div>
        {file ? (
          <p className="mt-3 text-xs text-[color:var(--light)]">
            {file.name} · {toFa(Math.ceil(file.size / 1024))} کیلوبایت
          </p>
        ) : null}
        {mutation.isError ? (
          <div className="mt-4">
            <Alert variant="danger">{errorMessage(mutation.error)}</Alert>
          </div>
        ) : null}
        {mutation.isPending ? (
          <p className="mt-4 text-xs leading-6 text-[color:var(--light)]" role="status">
            {
              {
                hashing: "در حال محاسبه SHA-256…",
                uploading: "در حال بارگذاری خصوصی…",
                processing: "در حال بررسی امنیتی و ساخت نسخه‌های WebP/AVIF…",
                idle: "در حال آماده‌سازی…",
              }[uploadStage]
            }
          </p>
        ) : null}
        <Button type="submit" className="mt-5 w-full" loading={mutation.isPending}>
          <ImagePlus size={16} />
          {retryUploadId ? "تلاش دوباره پردازش" : "محاسبه SHA-256 و آپلود"}
        </Button>
      </form>

      <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
        <div className="flex items-center justify-between gap-3">
          <div>
            <h2 className="font-bold">کتابخانه رسانه</h2>
            <p className="mt-1 text-xs text-[color:var(--light)]">
              {toFa(mediaQuery.data?.items.length ?? 0)} فایل
            </p>
          </div>
          <Button
            variant="outline"
            onClick={() => void mediaQuery.refetch()}
            loading={mediaQuery.isFetching}
          >
            <RefreshCw size={15} />
          </Button>
        </div>
        <div className="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3">
          {mediaQuery.isLoading ? (
            Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="aspect-square" />
            ))
          ) : mediaQuery.isError ? (
            <div className="col-span-full">
              <Alert variant="danger">{errorMessage(mediaQuery.error)}</Alert>
            </div>
          ) : mediaQuery.data?.items.length ? (
            mediaQuery.data.items.map((media) => (
              <figure
                key={media.id}
                className="overflow-hidden rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)]"
              >
                <img
                  src={bestMediaUrl(media) ?? undefined}
                  srcSet={mediaSrcSet(media)}
                  sizes="(min-width: 768px) 20vw, 50vw"
                  alt={media.alt}
                  className="aspect-square w-full object-cover"
                />
                <figcaption className="line-clamp-2 p-3 text-xs leading-5 text-[color:var(--light)]">
                  {media.alt}
                </figcaption>
              </figure>
            ))
          ) : (
            <div className="col-span-full">
              <EmptyState
                title="رسانه‌ای ثبت نشده"
                description="پس از فعال‌شدن R2 در Staging، تصاویر اینجا نمایش داده می‌شوند."
              />
            </div>
          )}
        </div>
      </section>
    </div>
  );
}

function SellerSettlementsWorkspace({ roastery }: { roastery: SellerRoastery }) {
  const settlementsQuery = useQuery(sellerSettlementsQueryOptions(roastery.id));

  if (settlementsQuery.isLoading) {
    return (
      <section className="grid gap-4 md:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className="h-28" />
        ))}
      </section>
    );
  }

  if (settlementsQuery.isError) {
    return <Alert variant="danger">{errorMessage(settlementsQuery.error)}</Alert>;
  }

  const data = settlementsQuery.data;
  if (!data) return null;

  const summaries = [
    { label: "در انتظار تحویل یا پایان مهلت اعتراض", value: data.summary.held },
    { label: "قابل افزودن به Batch", value: data.summary.eligible },
    { label: "در برنامه پرداخت", value: data.summary.scheduled },
    { label: "پرداخت‌شده", value: data.summary.paid },
  ];

  return (
    <div className="space-y-6">
      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {summaries.map((item) => (
          <article
            key={item.label}
            className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
          >
            <p className="text-xs leading-6 text-[color:var(--light)]">{item.label}</p>
            <p className="mt-3 font-mono-num text-xl font-bold text-[color:var(--steam)]">
              {formatIrr(item.value)}
            </p>
          </article>
        ))}
      </section>

      <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="font-bold">صورت‌حساب‌ها و پرداخت‌های روستری</h2>
            <p className="mt-1 text-xs leading-6 text-[color:var(--light)]">
              مبلغ سفارش هنگام ارسال آزاد نمی‌شود؛ تحویل قطعی، پایان مهلت اعتراض و نبود اختلاف شرط
              ورود به Batch پرداخت است.
            </p>
          </div>
          <Button
            variant="outline"
            onClick={() => void settlementsQuery.refetch()}
            loading={settlementsQuery.isFetching}
          >
            <RefreshCw size={15} />
            تازه‌سازی
          </Button>
        </div>

        <div className="mt-5 grid gap-3">
          {data.batches.length ? (
            data.batches.map((batch) => (
              <article
                key={batch.id}
                className="rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-mono-num text-sm font-bold">Batch {batch.id}</p>
                    <p className="mt-2 text-xs text-[color:var(--light)]">
                      {toFa(batch.allocation_count)} ردیف مالی · {formatDate(batch.scheduled_at)}
                    </p>
                  </div>
                  <StatusPill label={settlementBatchStatusLabel(batch.status)} />
                </div>
                <div className="mt-4 flex flex-wrap items-end justify-between gap-3 border-t border-[color:var(--mid)] pt-4">
                  <div>
                    <p className="text-xs text-[color:var(--light)]">خالص پرداخت</p>
                    <p className="mt-1 font-mono-num font-bold text-[color:var(--steam)]">
                      {formatIrr(batch.net_total)}
                    </p>
                  </div>
                  <p className="text-xs text-[color:var(--light)]">
                    {batch.payout_reference
                      ? `مرجع بانکی: ${batch.payout_reference}`
                      : batch.failed_at
                        ? `پرداخت ناموفق در ${formatDate(batch.failed_at)}`
                        : batch.paid_at
                          ? `پرداخت در ${formatDate(batch.paid_at)}`
                          : "در انتظار اقدام مالی رستا"}
                  </p>
                </div>
              </article>
            ))
          ) : (
            <EmptyState
              title="هنوز Batch تسویه‌ای ایجاد نشده"
              description="پس از تحویل قطعی و پایان مهلت اعتراض، مبالغ واجد شرایط توسط واحد مالی رستا در Batch قرار می‌گیرند."
            />
          )}
        </div>
      </section>
    </div>
  );
}

function settlementBatchStatusLabel(
  status: "pending" | "processing" | "requires_review" | "paid" | "failed",
) {
  return {
    pending: "در انتظار پردازش",
    processing: "در حال پرداخت",
    requires_review: "نیازمند بررسی مالی",
    paid: "پرداخت‌شده",
    failed: "ناموفق؛ قابل تلاش مجدد",
  }[status];
}

function StatusPill({ label, subtle = false }: { label: string; subtle?: boolean }) {
  return (
    <span
      className={`rounded-full border px-3 py-1 text-[10px] font-bold ${
        subtle
          ? "border-[color:var(--mid)] text-[color:var(--light)]"
          : "border-[color:var(--roast)]/50 bg-[color:var(--roast)]/10 text-[color:var(--roast)]"
      }`}
    >
      {label}
    </span>
  );
}

function errorMessage(error: unknown): string {
  return isApiError(error)
    ? error.message
    : error instanceof Error
      ? error.message
      : "عملیات پنل انجام نشد.";
}

function formatIrr(amount: number): string {
  return `${amount.toLocaleString("fa-IR")} ریال`;
}

function formatDate(value?: string | null): string {
  if (!value) return "زمان ثبت نشده";
  const timestamp = Date.parse(value);
  if (!Number.isFinite(timestamp)) return "زمان نامعتبر";
  return new Intl.DateTimeFormat("fa-IR", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: "Asia/Tehran",
  }).format(new Date(timestamp));
}

function digits(value: string): string {
  return value.replace(/[^0-9]/g, "");
}

function numberOrNull(value: string): number | null {
  return value.trim() ? Number(value) : null;
}

function commaList(value: string): string[] {
  return [
    ...new Set(
      value
        .split(/[،,]/)
        .map((item) => item.trim())
        .filter(Boolean),
    ),
  ].slice(0, 30);
}

function newLedgerIdempotencyKey(): string {
  const id =
    globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  return `stock:${id}`;
}

function roasteryStatusLabel(status: SellerRoastery["status"]): string {
  return {
    pending: "در انتظار بررسی",
    verified: "تأییدشده",
    suspended: "تعلیق‌شده",
    rejected: "ردشده",
  }[status];
}

function roleLabel(role: SellerRoastery["accessRoles"][number]): string {
  return {
    roastery_owner: "مالک",
    roastery_manager: "مدیر",
    roastery_staff: "کارمند",
    administrator: "ادمین",
  }[role];
}

function productStatusLabel(status: ProductSummary["status"]): string {
  return {
    draft: "پیش‌نویس",
    review: "در انتظار بررسی",
    published: "منتشرشده",
    archived: "بایگانی",
  }[status];
}

function subOrderStatusLabel(status: string): string {
  return (
    {
      awaiting_payment: "در انتظار پرداخت",
      pending_acceptance: "در انتظار پرداخت (قدیمی)",
      accepted: "پذیرفته‌شده",
      rejected: "ردشده",
      preparing: "در حال آماده‌سازی",
      ready_to_ship: "آماده ارسال",
      shipped: "ارسال‌شده",
      delivered: "تحویل‌شده",
      cancelled: "لغوشده",
      refund_pending: "در انتظار بازپرداخت",
      refunded: "بازپرداخت‌شده",
    }[status] ?? status
  );
}

function shipmentLegStatusLabel(status: string): string {
  return (
    {
      planned: "برنامه‌ریزی‌شده",
      picked_up: "تحویل‌گرفته‌شده",
      in_transit: "در مسیر هاب",
      delivered: "تحویل‌شده به هاب",
      failed: "ناموفق",
      cancelled: "لغوشده",
    }[status] ?? status
  );
}

function fulfillmentActions(status?: string): FulfillmentInput["status"][] {
  const actions: Partial<Record<string, FulfillmentInput["status"][]>> = {
    accepted: ["preparing"],
    preparing: ["ready_to_ship"],
    ready_to_ship: ["shipped"],
  };
  return actions[status ?? ""] ?? [];
}

function fulfillmentActionLabel(status: FulfillmentInput["status"]): string {
  return {
    preparing: "شروع آماده‌سازی",
    ready_to_ship: "آماده ارسال",
    shipped: "ثبت تحویل به حمل",
  }[status];
}

function stockReasonLabel(reason: string): string {
  return (
    {
      opening: "موجودی اولیه",
      purchase: "ورود خرید/تولید",
      correction: "اصلاح",
      damage: "آسیب",
      expiry: "انقضا",
      return: "مرجوعی",
      reservation_release: "آزادسازی رزرو",
    }[reason] ?? reason
  );
}
