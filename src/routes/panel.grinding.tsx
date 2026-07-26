import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link, Navigate } from "@tanstack/react-router";
import { Coffee, RefreshCw, Save } from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton, TextField } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import {
  grindingProfilesQueryOptions,
  sellerGrindingCapabilityQueryOptions,
  updateSellerGrindingCapability,
  type UpsertGrindingCapabilityInput,
} from "@/lib/api/grinding-capability";
import { sellerRoasteriesQueryOptions } from "@/lib/api/seller-operations";
import { toFa } from "@/lib/persian";

export const Route = createFileRoute("/panel/grinding")({
  head: () => ({
    meta: [
      { title: "تنظیم سرویس آسیاب روستری | رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: SellerGrindingPage,
});

const editableRoles = new Set(["roastery_owner", "roastery_manager", "administrator"]);
const weights = [50, 100, 250, 500, 1000] as const;
const fieldClass =
  "min-h-11 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

function SellerGrindingPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-6xl px-4 py-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Breadcrumb
            items={[
              { label: "خانه", to: "/" },
              { label: "پنل روستری", to: "/panel" },
              { label: "سرویس آسیاب" },
            ]}
          />
          <Link
            to="/panel"
            className="inline-flex min-h-11 items-center rounded-xl border border-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--roast)]"
          >
            بازگشت به عملیات
          </Link>
        </div>
        <AccountGuard>
          {(user) =>
            user.roles.some((role) => editableRoles.has(role)) ? (
              <GrindingCapabilityWorkspace />
            ) : (
              <Navigate to="/forbidden" replace />
            )
          }
        </AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function GrindingCapabilityWorkspace() {
  const queryClient = useQueryClient();
  const roasteriesQuery = useQuery(sellerRoasteriesQueryOptions());
  const profilesQuery = useQuery(grindingProfilesQueryOptions());
  const [roasteryId, setRoasteryId] = useState("");

  useEffect(() => {
    if (!roasteryId && roasteriesQuery.data?.length) {
      setRoasteryId(roasteriesQuery.data[0].id);
    }
  }, [roasteryId, roasteriesQuery.data]);

  const capabilityQuery = useQuery(sellerGrindingCapabilityQueryOptions(roasteryId));
  const [form, setForm] = useState<GrindingForm>(emptyForm());

  useEffect(() => {
    const capability = capabilityQuery.data;
    if (!capability) {
      if (!capabilityQuery.isPending) setForm(emptyForm());
      return;
    }

    setForm({
      availability: capability.availability,
      feeMode: capability.feeMode,
      feeAmount: String(capability.feeAmount),
      preparationMinutes: String(capability.preparationMinutes),
      capacityPerDay: capability.capacityPerDay ? String(capability.capacityPerDay) : "",
      supportedWeights: capability.supportedWeights,
      grindingProfileIds: capability.profiles.map((profile) => profile.id),
      isActive: capability.isActive,
    });
  }, [capabilityQuery.data, capabilityQuery.isPending, roasteryId]);

  const mutation = useMutation({
    mutationFn: (input: UpsertGrindingCapabilityInput) =>
      updateSellerGrindingCapability(roasteryId, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["seller", "roasteries", roasteryId, "grinding-capability"],
      });
      await queryClient.invalidateQueries({
        queryKey: ["catalog", "roasteries"],
      });
    },
  });

  if (roasteriesQuery.isLoading || profilesQuery.isLoading) {
    return <Skeleton className="mt-8 h-[38rem]" />;
  }

  if (roasteriesQuery.isError || profilesQuery.isError) {
    return (
      <div className="mt-8">
        <Alert variant="danger" title="اطلاعات سرویس آسیاب دریافت نشد">
          {errorMessage(roasteriesQuery.error || profilesQuery.error)}
        </Alert>
      </div>
    );
  }

  const roastery = roasteriesQuery.data?.find((item) => item.id === roasteryId);
  if (!roastery) {
    return (
      <div className="mt-8">
        <EmptyState
          title="روستری قابل ویرایشی پیدا نشد"
          description="مالک یا مدیر روستری باید به این صفحه دسترسی داشته باشد."
        />
      </div>
    );
  }

  const profiles = profilesQuery.data ?? [];
  const submit = (event: FormEvent) => {
    event.preventDefault();
    mutation.mutate({
      availability: form.availability,
      feeMode: form.feeMode,
      feeAmount: form.feeMode === "fixed" ? Number(form.feeAmount || 0) : 0,
      preparationMinutes: Number(form.preparationMinutes || 0),
      capacityPerDay: form.capacityPerDay ? Number(form.capacityPerDay) : null,
      supportedWeights: form.supportedWeights,
      grindingProfileIds: form.grindingProfileIds,
      isActive: form.isActive,
    });
  };

  return (
    <section className="mt-8 space-y-6">
      <header className="rounded-3xl border border-[color:var(--roast)]/40 bg-[color:var(--dark)] p-6 md:p-8">
        <div className="flex flex-wrap items-start justify-between gap-5">
          <div className="flex items-start gap-4">
            <span className="grid size-12 place-items-center rounded-2xl bg-[color:var(--roast)] text-[color:var(--night)]">
              <Coffee size={23} />
            </span>
            <div>
              <p className="text-xs font-bold tracking-[0.18em] text-[color:var(--roast)]">
                R5E GRINDING CAPABILITY
              </p>
              <h1 className="mt-2 text-3xl font-bold">تنظیم سرویس آسیاب روستری</h1>
              <p className="mt-3 max-w-3xl text-sm leading-8 text-[color:var(--light)]">
                این تنظیم فقط قابلیت ارائه سرویس را اعلام می‌کند. محصول، SKU، بچ رست و موجودی همچنان
                فقط دانه کامل باقی می‌مانند.
              </p>
            </div>
          </div>
          <div className="flex flex-wrap items-end gap-3">
            <label className="grid gap-2 text-xs font-bold">
              روستری فعال
              <select
                value={roasteryId}
                onChange={(event) => setRoasteryId(event.target.value)}
                className={fieldClass}
              >
                {roasteriesQuery.data?.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </label>
            <Button
              variant="outline"
              onClick={() => void capabilityQuery.refetch()}
              loading={capabilityQuery.isFetching}
            >
              <RefreshCw size={16} />
              تازه‌سازی
            </Button>
          </div>
        </div>
      </header>

      {capabilityQuery.isLoading ? (
        <Skeleton className="h-[34rem]" />
      ) : capabilityQuery.isError ? (
        <Alert variant="danger">{errorMessage(capabilityQuery.error)}</Alert>
      ) : (
        <form
          onSubmit={submit}
          className="rounded-3xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-6 md:p-8"
        >
          <div className="grid gap-6 lg:grid-cols-2">
            <div className="space-y-5">
              <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
                <h2 className="font-bold">وضعیت و هزینه</h2>
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                  <label className="grid gap-2 text-sm font-bold">
                    وضعیت ارائه
                    <select
                      value={form.availability}
                      onChange={(event) =>
                        setForm((current) => ({
                          ...current,
                          availability: event.target.value as GrindingForm["availability"],
                        }))
                      }
                      className={fieldClass}
                    >
                      <option value="available">قابل ارائه</option>
                      <option value="unavailable">غیرفعال</option>
                    </select>
                  </label>
                  <label className="grid gap-2 text-sm font-bold">
                    مدل هزینه
                    <select
                      value={form.feeMode}
                      onChange={(event) => {
                        const feeMode = event.target.value as GrindingForm["feeMode"];
                        setForm((current) => ({
                          ...current,
                          feeMode,
                          feeAmount: feeMode === "free" ? "0" : current.feeAmount,
                        }));
                      }}
                      className={fieldClass}
                    >
                      <option value="free">رایگان</option>
                      <option value="fixed">مبلغ ثابت</option>
                    </select>
                  </label>
                  <TextField
                    label="هزینه هر بسته (ریال)"
                    inputMode="numeric"
                    disabled={form.feeMode === "free"}
                    value={form.feeAmount}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        feeAmount: digits(event.target.value).slice(0, 16),
                      }))
                    }
                  />
                  <TextField
                    label="زمان افزوده آماده‌سازی (دقیقه)"
                    inputMode="numeric"
                    required
                    value={form.preparationMinutes}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        preparationMinutes: digits(event.target.value).slice(0, 5),
                      }))
                    }
                  />
                  <TextField
                    label="ظرفیت روزانه اختیاری"
                    inputMode="numeric"
                    value={form.capacityPerDay}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        capacityPerDay: digits(event.target.value).slice(0, 7),
                      }))
                    }
                  />
                  <label className="flex min-h-11 items-center gap-3 self-end rounded-xl border border-[color:var(--mid)] px-3 text-sm font-bold">
                    <input
                      type="checkbox"
                      checked={form.isActive}
                      onChange={(event) =>
                        setForm((current) => ({ ...current, isActive: event.target.checked }))
                      }
                    />
                    انتشار قابلیت فعال
                  </label>
                </div>
              </section>

              <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
                <h2 className="font-bold">وزن‌های دانه کامل قابل آسیاب</h2>
                <div className="mt-4 flex flex-wrap gap-2">
                  {weights.map((weight) => {
                    const checked = form.supportedWeights.includes(weight);
                    return (
                      <label
                        key={weight}
                        className={`inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm ${
                          checked
                            ? "border-[color:var(--roast)] text-[color:var(--steam)]"
                            : "border-[color:var(--mid)] text-[color:var(--light)]"
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() =>
                            setForm((current) => ({
                              ...current,
                              supportedWeights: toggleNumber(current.supportedWeights, weight),
                            }))
                          }
                        />
                        {toFa(weight)} گرم
                      </label>
                    );
                  })}
                </div>
              </section>
            </div>

            <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="font-bold">پروفایل‌های تأییدشده رستا</h2>
                  <p className="mt-2 text-xs leading-7 text-[color:var(--light)]">
                    تنظیم عددی دستگاه در کنترل عملیات رستا است؛ فروشنده فقط روش‌های قابل ارائه را
                    انتخاب می‌کند.
                  </p>
                </div>
                <span className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs text-[color:var(--roast)]">
                  {toFa(form.grindingProfileIds.length)} انتخاب
                </span>
              </div>
              <div className="mt-5 grid gap-3 sm:grid-cols-2">
                {profiles.map((profile) => {
                  const checked = form.grindingProfileIds.includes(profile.id);
                  return (
                    <label
                      key={profile.id}
                      className={`cursor-pointer rounded-2xl border p-4 ${
                        checked
                          ? "border-[color:var(--roast)] bg-[color:var(--dark)]"
                          : "border-[color:var(--mid)]"
                      }`}
                    >
                      <div className="flex items-start gap-3">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() =>
                            setForm((current) => ({
                              ...current,
                              grindingProfileIds: toggleString(
                                current.grindingProfileIds,
                                profile.id,
                              ),
                            }))
                          }
                        />
                        <div>
                          <p className="text-sm font-bold">{profile.publicName}</p>
                          <p className="mt-1 text-[10px] text-[color:var(--light)]">
                            {profile.code} · نسخه {toFa(profile.version)}
                          </p>
                        </div>
                      </div>
                    </label>
                  );
                })}
              </div>
            </section>
          </div>

          {mutation.isError ? (
            <div className="mt-5">
              <Alert variant="danger">{errorMessage(mutation.error)}</Alert>
            </div>
          ) : null}
          {mutation.isSuccess ? (
            <div className="mt-5">
              <Alert variant="success" title="تنظیم آسیاب ذخیره شد">
                قابلیت فعال از endpoint عمومی روستری در صفحه مشتری نمایش داده می‌شود.
              </Alert>
            </div>
          ) : null}

          <div className="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-[color:var(--mid)] pt-5">
            <p className="text-xs leading-7 text-[color:var(--light)]">
              برای انتشار حالت «قابل ارائه»، حداقل یک وزن و یک پروفایل باید انتخاب شود.
            </p>
            <Button type="submit" loading={mutation.isPending}>
              <Save size={17} />
              ذخیره تنظیم آسیاب
            </Button>
          </div>
        </form>
      )}
    </section>
  );
}

type GrindingForm = {
  availability: "available" | "unavailable";
  feeMode: "free" | "fixed";
  feeAmount: string;
  preparationMinutes: string;
  capacityPerDay: string;
  supportedWeights: Array<(typeof weights)[number]>;
  grindingProfileIds: string[];
  isActive: boolean;
};

function emptyForm(): GrindingForm {
  return {
    availability: "unavailable",
    feeMode: "free",
    feeAmount: "0",
    preparationMinutes: "20",
    capacityPerDay: "",
    supportedWeights: [250],
    grindingProfileIds: [],
    isActive: false,
  };
}

function digits(value: string): string {
  return value.replace(/\D/g, "");
}

function toggleNumber<T extends number>(values: T[], value: T): T[] {
  return values.includes(value)
    ? values.filter((current) => current !== value)
    : [...values, value].sort((first, second) => first - second);
}

function toggleString(values: string[], value: string): string[] {
  return values.includes(value)
    ? values.filter((current) => current !== value)
    : [...values, value];
}

function errorMessage(error: unknown): string {
  return isApiError(error)
    ? error.message
    : "ثبت تنظیم آسیاب انجام نشد. اتصال API و دسترسی نقش را بررسی کنید.";
}
