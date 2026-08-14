import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useMemo, useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton, TextField } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import {
  acceptSellerInvitation,
  createSellerClosure,
  createSellerInvitation,
  createSellerPromotion,
  removeSellerMember,
  revokeSellerClosure,
  revokeSellerInvitation,
  sellerOrganizationQueryOptions,
  updateSellerMemberRole,
  updateSellerPromotionStatus,
  updateSellerSchedule,
  type ScheduleExceptionInput,
  type SellerMembershipRole,
  type WeeklyHourInput,
} from "@/lib/api/seller-organization";
import { sellerRoasteriesQueryOptions } from "@/lib/api/seller-operations";

export const Route = createFileRoute("/panel/organization")({
  head: () => ({
    meta: [
      { title: "اعضا، دسترسی و ساعات روستری | رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: SellerOrganizationPage,
});

const fieldClass =
  "min-h-11 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)] focus-visible:ring-2 focus-visible:ring-[color:var(--roast)]";
const cardClass = "rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5";
const roleLabels: Record<SellerMembershipRole, string> = {
  owner: "مالک",
  manager: "مدیر",
  catalog: "کاتالوگ و موجودی",
  fulfillment: "آماده‌سازی و ارسال",
  finance: "مالی",
  support: "پشتیبانی",
};
const weekLabels = ["یکشنبه", "دوشنبه", "سه‌شنبه", "چهارشنبه", "پنجشنبه", "جمعه", "شنبه"];
const defaultWeekly: WeeklyHourInput[] = weekLabels.map((_, weekday) => ({
  weekday,
  is_closed: false,
  opens_at: "09:00",
  closes_at: "18:00",
}));

function message(error: unknown): string {
  return isApiError(error)
    ? error.message
    : "درخواست انجام نشد. اتصال و قرارداد API را بررسی کنید.";
}

function localInputToIso(value: string): string {
  const date = new Date(value);
  if (!Number.isFinite(date.getTime())) return "";
  return date.toISOString();
}

function SellerOrganizationPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8" dir="rtl">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "پنل روستری", to: "/panel" },
            { label: "اعضا و دسترسی" },
          ]}
        />
        <AccountGuard>{() => <OrganizationDashboard />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function OrganizationDashboard() {
  const roasteries = useQuery(sellerRoasteriesQueryOptions());
  const [roasteryId, setRoasteryId] = useState("");
  useEffect(() => {
    if (!roasteryId && roasteries.data?.length) setRoasteryId(roasteries.data[0].id);
  }, [roasteries.data, roasteryId]);

  if (roasteries.isLoading) return <Skeleton className="mt-8 h-96" />;
  if (roasteries.isError) return <Alert variant="danger">{message(roasteries.error)}</Alert>;

  return (
    <section className="mt-8 space-y-7">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-[0.18em] text-[color:var(--roast)]">
            SELLER ORGANIZATION
          </p>
          <h1 className="mt-2 text-3xl font-bold">اعضا، دسترسی و دسترس‌پذیری روستری</h1>
          <p className="mt-3 max-w-3xl text-sm leading-8 text-[color:var(--light)]">
            مجوزها در سرور و برای هر روستری جداگانه اعمال می‌شوند. ساعات کاری وضعیت عملیاتی را نشان
            می‌دهند؛ فقط تعطیلی موقتِ مسدودکننده، سفارش جدید را متوقف می‌کند.
          </p>
        </div>
        <div className="flex min-w-64 flex-wrap gap-3">
          {roasteries.data?.length ? (
            <label className="flex-1 text-xs font-bold text-[color:var(--light)]">
              روستری فعال
              <select
                aria-label="روستری فعال"
                value={roasteryId}
                onChange={(event) => setRoasteryId(event.target.value)}
                className={`${fieldClass} mt-2`}
              >
                {roasteries.data.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </label>
          ) : null}
          <Link
            to="/panel"
            className="inline-flex min-h-11 items-center self-end rounded-xl border border-[color:var(--roast)] px-4 text-sm font-bold text-[color:var(--roast)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)]"
          >
            عملیات روزانه
          </Link>
        </div>
      </header>

      <AcceptInviteCard />
      {roasteryId ? (
        <OrganizationWorkspace roasteryId={roasteryId} />
      ) : (
        <EmptyState title="هنوز عضویت فعالی برای این حساب ثبت نشده است" />
      )}
    </section>
  );
}

function AcceptInviteCard() {
  const client = useQueryClient();
  const [token, setToken] = useState("");
  const mutation = useMutation({
    mutationFn: () => acceptSellerInvitation(token),
    onSuccess: async () => {
      setToken("");
      await client.invalidateQueries({ queryKey: ["seller", "roasteries"] });
    },
  });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (/^[a-fA-F0-9]{64}$/.test(token.trim())) mutation.mutate();
  };

  return (
    <form onSubmit={submit} className={cardClass} aria-labelledby="accept-invite-title">
      <div className="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
        <TextField
          label="کد دعوت ۶۴ کاراکتری"
          id="seller-invite-token"
          dir="ltr"
          autoComplete="off"
          value={token}
          onChange={(event) => setToken(event.target.value.replace(/\s/g, ""))}
          aria-describedby="accept-invite-title"
        />
        <Button
          type="submit"
          loading={mutation.isPending}
          disabled={!/^[a-fA-F0-9]{64}$/.test(token)}
        >
          پذیرش دعوت
        </Button>
      </div>
      <p id="accept-invite-title" className="mt-3 text-xs leading-6 text-[color:var(--light)]">
        دعوت فقط برای همان حساب/شماره مقصد معتبر است و پس از پذیرش دوباره قابل استفاده نیست.
      </p>
      {mutation.isSuccess ? (
        <div className="mt-3">
          <Alert>عضویت با موفقیت فعال شد.</Alert>
        </div>
      ) : null}
      {mutation.isError ? (
        <div className="mt-3">
          <Alert variant="danger">{message(mutation.error)}</Alert>
        </div>
      ) : null}
    </form>
  );
}

function OrganizationWorkspace({ roasteryId }: { roasteryId: string }) {
  const organization = useQuery(sellerOrganizationQueryOptions(roasteryId));
  if (organization.isLoading) return <Skeleton className="h-[38rem]" />;
  if (organization.isError) return <Alert variant="danger">{message(organization.error)}</Alert>;
  if (!organization.data) return <EmptyState title="اطلاعات سازمان روستری در دسترس نیست" />;

  const data = organization.data;
  const canManage = data.permissions.includes("organization.manage");
  const canSchedule = data.permissions.includes("availability.write");
  const canPromote = data.permissions.includes("promotion.write");

  return (
    <div className="space-y-7">
      <section className={cardClass} aria-labelledby="access-summary-title">
        <div className="grid gap-5 md:grid-cols-3">
          <div>
            <h2 id="access-summary-title" className="text-xl font-bold">
              دسترسی فعلی
            </h2>
            <p className="mt-2 text-sm text-[color:var(--light)]">
              نقش: {data.role ? roleLabels[data.role] : "فقط نظارت ادمین"}
            </p>
          </div>
          <div>
            <p className="text-xs font-bold text-[color:var(--roast)]">وضعیت عملیاتی</p>
            <p className="mt-2 text-sm font-bold">
              {data.availability.status === "open"
                ? "باز"
                : data.availability.status === "temporarily_closed"
                  ? "تعطیل موقت"
                  : "خارج ساعات کاری"}
            </p>
            <p className="mt-1 text-xs text-[color:var(--light)]">
              Timezone: {data.availability.timezone}
            </p>
          </div>
          <div>
            <p className="text-xs font-bold text-[color:var(--roast)]">سفارش جدید</p>
            <p className="mt-2 text-sm font-bold">
              {data.availability.accepting_orders ? "پذیرفته می‌شود" : "موقتاً مسدود است"}
            </p>
            {data.availability.closed_until ? (
              <p className="mt-1 text-xs text-[color:var(--light)]">
                تا {new Date(data.availability.closed_until).toLocaleString("fa-IR")}
              </p>
            ) : null}
          </div>
        </div>
        <div className="mt-5 flex flex-wrap gap-2" aria-label="مجوزهای مؤثر">
          {data.permissions.map((permission) => (
            <span
              key={permission}
              className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs text-[color:var(--light)]"
              dir="ltr"
            >
              {permission}
            </span>
          ))}
        </div>
      </section>

      <MembersCard roasteryId={roasteryId} canManage={canManage} members={data.members} />
      <InvitationsCard
        roasteryId={roasteryId}
        canManage={canManage}
        invitations={data.invitations}
      />
      <ScheduleCard
        roasteryId={roasteryId}
        canWrite={canSchedule}
        timezone={data.timezone}
        weekly={data.weekly_hours}
        exceptions={data.exceptions}
      />
      <ClosuresCard roasteryId={roasteryId} canWrite={canSchedule} closures={data.closures} />
      <PromotionsCard roasteryId={roasteryId} canWrite={canPromote} promotions={data.promotions} />
    </div>
  );
}

function MembersCard({
  roasteryId,
  canManage,
  members,
}: {
  roasteryId: string;
  canManage: boolean;
  members: Array<{
    id: string;
    name: string | null;
    mobile: string | null;
    role: SellerMembershipRole;
    is_locked: boolean;
  }>;
}) {
  const client = useQueryClient();
  const mutation = useMutation({
    mutationFn: ({ id, role }: { id: string; role: SellerMembershipRole }) =>
      updateSellerMemberRole(roasteryId, id, role),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] }),
  });
  const remove = useMutation({
    mutationFn: (id: string) => removeSellerMember(roasteryId, id),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] }),
  });

  return (
    <section className={cardClass} aria-labelledby="members-title">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="members-title" className="text-xl font-bold">
            اعضا و نقش‌ها
          </h2>
          <p className="mt-1 text-xs text-[color:var(--light)]">
            آخرین مالک فعال قابل حذف، تنزل نقش یا قفل‌شدن نیست.
          </p>
        </div>
        {!canManage ? <span className="text-xs text-[color:var(--light)]">فقط مشاهده</span> : null}
      </div>
      <div className="mt-5 grid gap-3">
        {members.map((member) => (
          <article
            key={member.id}
            className="grid gap-3 rounded-xl border border-[color:var(--mid)] p-4 md:grid-cols-[1fr_12rem_auto] md:items-center"
          >
            <div>
              <p className="font-bold">{member.name || "عضو روستری"}</p>
              <p className="mt-1 text-xs text-[color:var(--light)]" dir="ltr">
                {member.mobile || "شماره ثبت نشده"}
              </p>
              {member.is_locked ? (
                <p className="mt-1 text-xs font-bold text-amber-300">دسترسی توسط ادمین قفل شده</p>
              ) : null}
            </div>
            <label className="text-xs font-bold text-[color:var(--light)]">
              نقش
              <select
                value={member.role}
                disabled={!canManage || member.is_locked || mutation.isPending}
                onChange={(event) => {
                  const next = event.target.value as SellerMembershipRole;
                  if (window.confirm(`نقش این عضو به «${roleLabels[next]}» تغییر کند؟`)) {
                    mutation.mutate({ id: member.id, role: next });
                  }
                }}
                className={`${fieldClass} mt-2`}
              >
                {Object.entries(roleLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
            <Button
              type="button"
              variant="secondary"
              disabled={!canManage || member.is_locked || remove.isPending}
              onClick={() => {
                if (window.confirm("حذف عضویت قطعی است. ادامه می‌دهید؟")) remove.mutate(member.id);
              }}
            >
              حذف
            </Button>
          </article>
        ))}
      </div>
      {mutation.isError || remove.isError ? (
        <div className="mt-4">
          <Alert variant="danger">{message(mutation.error ?? remove.error)}</Alert>
        </div>
      ) : null}
    </section>
  );
}

function InvitationsCard({
  roasteryId,
  canManage,
  invitations,
}: {
  roasteryId: string;
  canManage: boolean;
  invitations: Array<{
    id: string;
    mobile: string | null;
    role: SellerMembershipRole;
    status: string;
    expires_at: string;
  }>;
}) {
  const client = useQueryClient();
  const [mobile, setMobile] = useState("");
  const [role, setRole] = useState<SellerMembershipRole>("catalog");
  const [issuedToken, setIssuedToken] = useState("");
  const create = useMutation({
    mutationFn: () => createSellerInvitation(roasteryId, mobile, role),
    onSuccess: async (result) => {
      setIssuedToken(result.token);
      setMobile("");
      await client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] });
    },
  });
  const revoke = useMutation({
    mutationFn: (id: string) => revokeSellerInvitation(roasteryId, id),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] }),
  });
  const submit = (event: FormEvent) => {
    event.preventDefault();
    setIssuedToken("");
    create.mutate();
  };

  return (
    <section className={cardClass} aria-labelledby="invites-title">
      <h2 id="invites-title" className="text-xl font-bold">
        دعوت عضو
      </h2>
      <form
        onSubmit={submit}
        className="mt-5 grid gap-4 md:grid-cols-[1fr_14rem_auto] md:items-end"
      >
        <TextField
          label="شماره موبایل مقصد"
          dir="ltr"
          inputMode="tel"
          disabled={!canManage}
          value={mobile}
          onChange={(event) => setMobile(event.target.value)}
          placeholder="09123456789"
          required
        />
        <label className="text-xs font-bold text-[color:var(--light)]">
          نقش دعوت
          <select
            value={role}
            disabled={!canManage}
            onChange={(event) => setRole(event.target.value as SellerMembershipRole)}
            className={`${fieldClass} mt-2`}
          >
            {Object.entries(roleLabels).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <Button type="submit" loading={create.isPending} disabled={!canManage || !mobile.trim()}>
          ارسال دعوت
        </Button>
      </form>
      {issuedToken ? (
        <div className="mt-4 rounded-xl border border-emerald-700/60 p-4" role="status">
          <p className="font-bold">توکن فقط همین بار نمایش داده می‌شود</p>
          <code className="mt-2 block break-all text-xs" dir="ltr">
            {issuedToken}
          </code>
        </div>
      ) : null}
      <div className="mt-5 grid gap-2">
        {invitations.length === 0 ? (
          <p className="text-sm text-[color:var(--light)]">دعوتی ثبت نشده است.</p>
        ) : (
          invitations.map((invite) => (
            <div
              key={invite.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[color:var(--mid)] p-3 text-sm"
            >
              <div>
                <span dir="ltr">{invite.mobile}</span>
                <span className="mx-2 text-[color:var(--light)]">•</span>
                <span>{roleLabels[invite.role]}</span>
                <span className="mx-2 text-[color:var(--light)]">•</span>
                <span>{invite.status}</span>
              </div>
              {invite.status === "pending" ? (
                <Button
                  type="button"
                  variant="secondary"
                  disabled={!canManage || revoke.isPending}
                  onClick={() => {
                    if (window.confirm("این دعوت لغو شود؟")) revoke.mutate(invite.id);
                  }}
                >
                  لغو دعوت
                </Button>
              ) : null}
            </div>
          ))
        )}
      </div>
      {create.isError || revoke.isError ? (
        <div className="mt-4">
          <Alert variant="danger">{message(create.error ?? revoke.error)}</Alert>
        </div>
      ) : null}
    </section>
  );
}

function ScheduleCard({
  roasteryId,
  canWrite,
  timezone,
  weekly,
  exceptions,
}: {
  roasteryId: string;
  canWrite: boolean;
  timezone: string;
  weekly: WeeklyHourInput[];
  exceptions: ScheduleExceptionInput[];
}) {
  const client = useQueryClient();
  const [tz, setTz] = useState(timezone);
  const [hours, setHours] = useState<WeeklyHourInput[]>(weekly.length ? weekly : defaultWeekly);
  const [specials, setSpecials] = useState<ScheduleExceptionInput[]>(exceptions);
  useEffect(() => {
    setTz(timezone);
    setHours(weekly.length ? weekly : defaultWeekly);
    setSpecials(exceptions);
  }, [timezone, weekly, exceptions]);
  const mutation = useMutation({
    mutationFn: () => updateSellerSchedule(roasteryId, tz, hours, specials),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] }),
  });

  return (
    <section className={cardClass} aria-labelledby="schedule-title">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id="schedule-title" className="text-xl font-bold">
            ساعات هفتگی و استثناها
          </h2>
          <p className="mt-1 text-xs leading-6 text-[color:var(--light)]">
            ساعت پایان کمتر از شروع به‌صورت بازه شبانه تا روز بعد تفسیر می‌شود. DST بر اساس timezone
            محاسبه می‌شود.
          </p>
        </div>
        <Button
          type="button"
          loading={mutation.isPending}
          disabled={!canWrite}
          onClick={() => mutation.mutate()}
        >
          ذخیره برنامه
        </Button>
      </div>
      <label className="mt-5 block max-w-md text-xs font-bold text-[color:var(--light)]">
        Timezone IANA
        <input
          value={tz}
          disabled={!canWrite}
          onChange={(event) => setTz(event.target.value)}
          className={`${fieldClass} mt-2`}
          list="rosta-timezones"
          dir="ltr"
        />
        <datalist id="rosta-timezones">
          <option value="Asia/Tehran" />
          <option value="Europe/Amsterdam" />
          <option value="UTC" />
        </datalist>
      </label>
      <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {hours.map((item, index) => (
          <fieldset
            key={item.weekday}
            className="rounded-xl border border-[color:var(--mid)] p-3"
            disabled={!canWrite}
          >
            <legend className="px-2 font-bold">{weekLabels[item.weekday]}</legend>
            <label className="flex min-h-11 items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={item.is_closed}
                onChange={(event) =>
                  setHours((current) =>
                    current.map((row, rowIndex) =>
                      rowIndex === index ? { ...row, is_closed: event.target.checked } : row,
                    ),
                  )
                }
              />
              تعطیل
            </label>
            <div className="grid grid-cols-2 gap-2">
              <label className="text-xs">
                شروع
                <input
                  aria-label={`${weekLabels[item.weekday]} شروع`}
                  type="time"
                  value={item.opens_at ?? "09:00"}
                  disabled={item.is_closed || !canWrite}
                  onChange={(event) =>
                    setHours((current) =>
                      current.map((row, rowIndex) =>
                        rowIndex === index ? { ...row, opens_at: event.target.value } : row,
                      ),
                    )
                  }
                  className={`${fieldClass} mt-1`}
                />
              </label>
              <label className="text-xs">
                پایان
                <input
                  aria-label={`${weekLabels[item.weekday]} پایان`}
                  type="time"
                  value={item.closes_at ?? "18:00"}
                  disabled={item.is_closed || !canWrite}
                  onChange={(event) =>
                    setHours((current) =>
                      current.map((row, rowIndex) =>
                        rowIndex === index ? { ...row, closes_at: event.target.value } : row,
                      ),
                    )
                  }
                  className={`${fieldClass} mt-1`}
                />
              </label>
            </div>
          </fieldset>
        ))}
      </div>
      <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
        <h3 className="font-bold">تاریخ‌های استثنا</h3>
        <Button
          type="button"
          variant="secondary"
          disabled={!canWrite || specials.length >= 180}
          onClick={() =>
            setSpecials((current) => [
              ...current,
              {
                local_date: new Date().toISOString().slice(0, 10),
                is_closed: true,
                opens_at: null,
                closes_at: null,
                public_reason: null,
              },
            ])
          }
        >
          افزودن تاریخ
        </Button>
      </div>
      <div className="mt-3 grid gap-3">
        {specials.map((special, index) => (
          <fieldset
            key={`${special.local_date}-${index}`}
            className="grid gap-3 rounded-xl border border-[color:var(--mid)] p-3 md:grid-cols-[10rem_auto_8rem_8rem_1fr_auto] md:items-end"
            disabled={!canWrite}
          >
            <label className="text-xs">
              تاریخ
              <input
                type="date"
                value={special.local_date}
                onChange={(event) =>
                  setSpecials((current) =>
                    current.map((row, rowIndex) =>
                      rowIndex === index ? { ...row, local_date: event.target.value } : row,
                    ),
                  )
                }
                className={`${fieldClass} mt-1`}
              />
            </label>
            <label className="flex min-h-11 items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={special.is_closed}
                onChange={(event) =>
                  setSpecials((current) =>
                    current.map((row, rowIndex) =>
                      rowIndex === index ? { ...row, is_closed: event.target.checked } : row,
                    ),
                  )
                }
              />
              کل روز تعطیل
            </label>
            <label className="text-xs">
              شروع
              <input
                type="time"
                value={special.opens_at ?? "09:00"}
                disabled={special.is_closed || !canWrite}
                onChange={(event) =>
                  setSpecials((current) =>
                    current.map((row, rowIndex) =>
                      rowIndex === index ? { ...row, opens_at: event.target.value } : row,
                    ),
                  )
                }
                className={`${fieldClass} mt-1`}
              />
            </label>
            <label className="text-xs">
              پایان
              <input
                type="time"
                value={special.closes_at ?? "18:00"}
                disabled={special.is_closed || !canWrite}
                onChange={(event) =>
                  setSpecials((current) =>
                    current.map((row, rowIndex) =>
                      rowIndex === index ? { ...row, closes_at: event.target.value } : row,
                    ),
                  )
                }
                className={`${fieldClass} mt-1`}
              />
            </label>
            <label className="text-xs">
              دلیل عمومی امن
              <input
                maxLength={180}
                value={special.public_reason ?? ""}
                onChange={(event) =>
                  setSpecials((current) =>
                    current.map((row, rowIndex) =>
                      rowIndex === index
                        ? { ...row, public_reason: event.target.value || null }
                        : row,
                    ),
                  )
                }
                className={`${fieldClass} mt-1`}
              />
            </label>
            <Button
              type="button"
              variant="secondary"
              onClick={() =>
                setSpecials((current) => current.filter((_, rowIndex) => rowIndex !== index))
              }
            >
              حذف
            </Button>
          </fieldset>
        ))}
      </div>
      {mutation.isSuccess ? (
        <div className="mt-4">
          <Alert>برنامه کاری ذخیره شد.</Alert>
        </div>
      ) : null}
      {mutation.isError ? (
        <div className="mt-4">
          <Alert variant="danger">{message(mutation.error)}</Alert>
        </div>
      ) : null}
    </section>
  );
}

function ClosuresCard({
  roasteryId,
  canWrite,
  closures,
}: {
  roasteryId: string;
  canWrite: boolean;
  closures: Array<{
    id: string;
    starts_at: string;
    ends_at: string;
    public_reason: string | null;
    blocks_new_orders: boolean;
    is_active: boolean;
    revoked_at: string | null;
  }>;
}) {
  const client = useQueryClient();
  const [startsAt, setStartsAt] = useState("");
  const [endsAt, setEndsAt] = useState("");
  const [reason, setReason] = useState("");
  const [blocks, setBlocks] = useState(true);
  const create = useMutation({
    mutationFn: () =>
      createSellerClosure(roasteryId, {
        startsAt: localInputToIso(startsAt),
        endsAt: localInputToIso(endsAt),
        publicReason: reason,
        blocksNewOrders: blocks,
      }),
    onSuccess: async () => {
      setReason("");
      setStartsAt("");
      setEndsAt("");
      await client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] });
    },
  });
  const revoke = useMutation({
    mutationFn: (id: string) => revokeSellerClosure(roasteryId, id),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] }),
  });

  return (
    <section className={cardClass} aria-labelledby="closure-title">
      <h2 id="closure-title" className="text-xl font-bold">
        تعطیلی موقت
      </h2>
      <p className="mt-2 text-xs leading-6 text-[color:var(--light)]">
        پس از زمان پایان، روستری خودکار باز می‌شود. دلیل عمومی نباید شماره تماس، ایمیل یا لینک داشته
        باشد.
      </p>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          if (window.confirm("تعطیلی موقت با این بازه ثبت شود؟")) create.mutate();
        }}
        className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5 xl:items-end"
      >
        <label className="text-xs font-bold">
          شروع
          <input
            type="datetime-local"
            required
            disabled={!canWrite}
            value={startsAt}
            onChange={(event) => setStartsAt(event.target.value)}
            className={`${fieldClass} mt-2`}
          />
        </label>
        <label className="text-xs font-bold">
          پایان
          <input
            type="datetime-local"
            required
            disabled={!canWrite}
            value={endsAt}
            onChange={(event) => setEndsAt(event.target.value)}
            className={`${fieldClass} mt-2`}
          />
        </label>
        <label className="text-xs font-bold xl:col-span-2">
          دلیل عمومی
          <input
            maxLength={180}
            disabled={!canWrite}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            className={`${fieldClass} mt-2`}
          />
        </label>
        <div className="space-y-2">
          <label className="flex min-h-11 items-center gap-2 text-xs font-bold">
            <input
              type="checkbox"
              disabled={!canWrite}
              checked={blocks}
              onChange={(event) => setBlocks(event.target.checked)}
            />
            مسدودکردن سفارش جدید
          </label>
          <Button
            type="submit"
            loading={create.isPending}
            disabled={!canWrite || !startsAt || !endsAt}
          >
            ثبت تعطیلی
          </Button>
        </div>
      </form>
      <div className="mt-5 grid gap-2">
        {closures.map((closure) => (
          <div
            key={closure.id}
            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[color:var(--mid)] p-3 text-sm"
          >
            <div>
              <p className="font-bold">
                {closure.is_active
                  ? "فعال"
                  : closure.revoked_at
                    ? "لغوشده"
                    : "زمان‌بندی‌شده/پایان‌یافته"}
              </p>
              <p className="mt-1 text-xs text-[color:var(--light)]">
                {new Date(closure.starts_at).toLocaleString("fa-IR")} ←{" "}
                {new Date(closure.ends_at).toLocaleString("fa-IR")}
              </p>
              {closure.public_reason ? (
                <p className="mt-1 text-xs">{closure.public_reason}</p>
              ) : null}
            </div>
            {!closure.revoked_at && new Date(closure.ends_at).getTime() > Date.now() ? (
              <Button
                type="button"
                variant="secondary"
                disabled={!canWrite || revoke.isPending}
                onClick={() => {
                  if (window.confirm("این تعطیلی لغو و پذیرش سفارش طبق برنامه ادامه پیدا کند؟"))
                    revoke.mutate(closure.id);
                }}
              >
                لغو تعطیلی
              </Button>
            ) : null}
          </div>
        ))}
      </div>
      {create.isError || revoke.isError ? (
        <div className="mt-4">
          <Alert variant="danger">{message(create.error ?? revoke.error)}</Alert>
        </div>
      ) : null}
    </section>
  );
}

function PromotionsCard({
  roasteryId,
  canWrite,
  promotions,
}: {
  roasteryId: string;
  canWrite: boolean;
  promotions: Array<{
    id: string;
    name: string;
    status: "draft" | "scheduled" | "paused" | "expired";
    starts_at: string | null;
    ends_at: string | null;
    pricing_applied: false;
  }>;
}) {
  const client = useQueryClient();
  const [name, setName] = useState("");
  const [startsAt, setStartsAt] = useState("");
  const [endsAt, setEndsAt] = useState("");
  const create = useMutation({
    mutationFn: () =>
      createSellerPromotion(roasteryId, {
        name,
        startsAt: startsAt ? localInputToIso(startsAt) : undefined,
        endsAt: endsAt ? localInputToIso(endsAt) : undefined,
      }),
    onSuccess: async () => {
      setName("");
      setStartsAt("");
      setEndsAt("");
      await client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] });
    },
  });
  const status = useMutation({
    mutationFn: ({
      id,
      value,
    }: {
      id: string;
      value: "draft" | "scheduled" | "paused" | "expired";
    }) => updateSellerPromotionStatus(roasteryId, id, value),
    onSuccess: async () =>
      client.invalidateQueries({ queryKey: ["seller", "organization", roasteryId] }),
  });

  return (
    <section className={cardClass} aria-labelledby="promotion-title">
      <h2 id="promotion-title" className="text-xl font-bold">
        Promotion lifecycle
      </h2>
      <p className="mt-2 text-xs leading-6 text-[color:var(--light)]">
        این فاز فقط چرخه draft/scheduled/paused/expired را ثبت می‌کند. هیچ قیمت، درصد تخفیف یا
        Coupon در این صفحه و API اعمال نمی‌شود.
      </p>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          create.mutate();
        }}
        className="mt-5 grid gap-4 md:grid-cols-4 md:items-end"
      >
        <TextField
          label="نام داخلی Promotion"
          value={name}
          disabled={!canWrite}
          onChange={(event) => setName(event.target.value)}
          required
        />
        <label className="text-xs font-bold">
          شروع اختیاری
          <input
            type="datetime-local"
            disabled={!canWrite}
            value={startsAt}
            onChange={(event) => setStartsAt(event.target.value)}
            className={`${fieldClass} mt-2`}
          />
        </label>
        <label className="text-xs font-bold">
          پایان اختیاری
          <input
            type="datetime-local"
            disabled={!canWrite}
            value={endsAt}
            onChange={(event) => setEndsAt(event.target.value)}
            className={`${fieldClass} mt-2`}
          />
        </label>
        <Button type="submit" loading={create.isPending} disabled={!canWrite || !name.trim()}>
          ایجاد قرارداد
        </Button>
      </form>
      <div className="mt-5 grid gap-2">
        {promotions.map((promotion) => (
          <div
            key={promotion.id}
            className="grid gap-3 rounded-xl border border-[color:var(--mid)] p-3 md:grid-cols-[1fr_12rem] md:items-center"
          >
            <div>
              <p className="font-bold">{promotion.name}</p>
              <p className="mt-1 text-xs text-[color:var(--light)]">
                قیمت اعمال‌شده: خیر • وضعیت: {promotion.status}
              </p>
            </div>
            <label className="text-xs font-bold">
              وضعیت
              <select
                value={promotion.status}
                disabled={!canWrite || promotion.status === "expired" || status.isPending}
                onChange={(event) => {
                  const value = event.target.value as "draft" | "scheduled" | "paused" | "expired";
                  if (window.confirm("وضعیت Promotion تغییر کند؟"))
                    status.mutate({ id: promotion.id, value });
                }}
                className={`${fieldClass} mt-1`}
              >
                <option value="draft">draft</option>
                <option value="scheduled">scheduled</option>
                <option value="paused">paused</option>
                <option value="expired">expired</option>
              </select>
            </label>
          </div>
        ))}
      </div>
      {create.isError || status.isError ? (
        <div className="mt-4">
          <Alert variant="danger">{message(create.error ?? status.error)}</Alert>
        </div>
      ) : null}
    </section>
  );
}
