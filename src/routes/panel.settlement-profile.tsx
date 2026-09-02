import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState, type FormEvent } from "react";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button, EmptyState, Skeleton, TextField } from "@/components/system";
import { isApiError } from "@/lib/api/client";
import { sellerRoasteriesQueryOptions } from "@/lib/api/seller-operations";
import {
  getSellerSettlementProfile,
  updateSellerSettlementProfile,
  type SettlementEntityType,
} from "@/lib/api/settlement-profiles";

export const Route = createFileRoute("/panel/settlement-profile")({
  head: () => ({
    meta: [
      { title: "مقصد تسویه روستری | رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: SellerSettlementProfilePage,
});

const fieldClass =
  "min-h-11 w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

function SellerSettlementProfilePage() {
  return (
    <>
      <Navbar />
      <main dir="rtl" className="mx-auto max-w-4xl px-4 py-8">
        <Breadcrumb
          items={[
            { label: "خانه", to: "/" },
            { label: "پنل روستری", to: "/panel" },
            { label: "مقصد تسویه" },
          ]}
        />
        <AccountGuard>{() => <SettlementWorkspace />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function SettlementWorkspace() {
  const roasteries = useQuery(sellerRoasteriesQueryOptions());
  const [roasteryId, setRoasteryId] = useState("");

  useEffect(() => {
    if (!roasteryId && roasteries.data?.length) setRoasteryId(roasteries.data[0].id);
  }, [roasteries.data, roasteryId]);

  if (roasteries.isLoading) return <Skeleton className="h-96" />;
  if (roasteries.isError) {
    return <Alert variant="danger">{isApiError(roasteries.error) ? roasteries.error.message : "روستری‌ها دریافت نشدند."}</Alert>;
  }
  if (!roasteries.data?.length) return <EmptyState title="روستری فعالی برای این حساب پیدا نشد" />;

  const selected = roasteries.data.find((item) => item.id === roasteryId) ?? roasteries.data[0];
  const canEdit = selected.accessRoles.some((role) =>
    ["roastery_owner", "roastery_manager"].includes(role),
  );

  return (
    <section className="mt-6 space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-[0.18em] text-[color:var(--roast)]">SETTLEMENT PROFILE</p>
          <h1 className="mt-2 text-3xl font-bold">مقصد تسویه روستری</h1>
          <p className="mt-3 max-w-2xl text-sm leading-7 text-[color:var(--light)]">
            مقصد تسویه پس از هر تغییر دوباره وارد صف بررسی ادمین می‌شود. تا وضعیت verified نباشد،
            Backend اجازه ساخت Settlement Batch برای این روستری را نمی‌دهد.
          </p>
        </div>
        <select value={selected.id} onChange={(event) => setRoasteryId(event.target.value)} className={fieldClass} aria-label="روستری فعال">
          {roasteries.data.map((item) => (
            <option key={item.id} value={item.id}>{item.name}</option>
          ))}
        </select>
      </header>

      <ProfileEditor key={selected.id} roasteryId={selected.id} canEdit={canEdit} />

      <Link to="/panel" className="inline-flex text-sm font-bold text-[color:var(--roast)] underline">
        بازگشت به پنل
      </Link>
    </section>
  );
}

function ProfileEditor({ roasteryId, canEdit }: { roasteryId: string; canEdit: boolean }) {
  const client = useQueryClient();
  const query = useQuery({
    queryKey: ["seller", roasteryId, "settlement-profile"],
    queryFn: () => getSellerSettlementProfile(roasteryId),
    staleTime: 15_000,
  });
  const [entityType, setEntityType] = useState<SettlementEntityType>("individual");
  const [legalName, setLegalName] = useState("");
  const [accountHolderName, setAccountHolderName] = useState("");
  const [iban, setIban] = useState("");

  useEffect(() => {
    if (!query.data) return;
    setEntityType(query.data.entity_type);
    setLegalName(query.data.legal_name);
    setAccountHolderName(query.data.account_holder_name);
    setIban("");
  }, [query.data]);

  const mutation = useMutation({
    mutationFn: () =>
      updateSellerSettlementProfile(roasteryId, {
        entityType,
        legalName,
        accountHolderName,
        iban,
      }),
    onSuccess: async (profile) => {
      client.setQueryData(["seller", roasteryId, "settlement-profile"], profile);
      setIban("");
    },
  });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!canEdit || !/^IR\d{24}$/.test(iban.replace(/\s+/g, "").toUpperCase())) return;
    mutation.mutate();
  };

  if (query.isLoading) return <Skeleton className="h-96" />;
  if (query.isError) {
    return <Alert variant="danger">{isApiError(query.error) ? query.error.message : "پروفایل تسویه دریافت نشد."}</Alert>;
  }

  const profile = query.data;
  const statusLabel = profile?.status === "verified"
    ? "تأییدشده"
    : profile?.status === "rejected"
      ? "ردشده"
      : profile
        ? "در انتظار بررسی"
        : "ثبت‌نشده";

  return (
    <form onSubmit={submit} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">اطلاعات مقصد</h2>
          <p className="mt-1 text-xs text-[color:var(--light)]">وضعیت: {statusLabel}</p>
        </div>
        {profile?.iban_masked ? <span dir="ltr" className="font-mono text-sm">{profile.iban_masked}</span> : null}
      </div>

      {profile?.status === "verified" ? (
        <div className="mt-4"><Alert variant="success">این مقصد برای ساخت Batch تسویه معتبر است.</Alert></div>
      ) : null}
      {profile?.status === "rejected" ? (
        <div className="mt-4"><Alert variant="warning" title="نیاز به اصلاح">{profile.review_note || "اطلاعات را اصلاح و دوباره ارسال کنید."}</Alert></div>
      ) : null}
      {!canEdit ? (
        <div className="mt-4"><Alert variant="info">نقش شما فقط اجازه مشاهده اطلاعات مالی را دارد؛ تغییر مقصد فقط برای مالک یا مدیر مجاز است.</Alert></div>
      ) : null}

      <div className="mt-5 grid gap-4 md:grid-cols-2">
        <label className="text-xs font-bold text-[color:var(--light)]">
          نوع شخصیت
          <select value={entityType} disabled={!canEdit} onChange={(event) => setEntityType(event.target.value as SettlementEntityType)} className={`${fieldClass} mt-2`}>
            <option value="individual">حقیقی</option>
            <option value="company">حقوقی</option>
          </select>
        </label>
        <TextField label="نام قانونی" id="settlement-legal-name" value={legalName} disabled={!canEdit} required onChange={(event) => setLegalName(event.target.value)} />
        <TextField label="نام صاحب حساب" id="settlement-holder-name" value={accountHolderName} disabled={!canEdit} required onChange={(event) => setAccountHolderName(event.target.value)} />
        <TextField label="شماره شبا" id="settlement-iban" dir="ltr" placeholder="IR000000000000000000000000" value={iban} disabled={!canEdit} required onChange={(event) => setIban(event.target.value.toUpperCase())} />
      </div>

      <p className="mt-4 text-xs leading-6 text-[color:var(--light)]">
        شماره شبا پس از ثبت به‌صورت encrypted در Backend نگه‌داری می‌شود و در پنل فروشنده فقط نسخه ماسک‌شده برمی‌گردد.
      </p>

      {canEdit ? (
        <Button className="mt-5" type="submit" loading={mutation.isPending} disabled={!legalName.trim() || !accountHolderName.trim() || !/^IR\d{24}$/.test(iban.replace(/\s+/g, "").toUpperCase())}>
          ارسال برای بررسی ادمین
        </Button>
      ) : null}

      {mutation.isSuccess ? <div className="mt-4"><Alert variant="success">پروفایل برای بررسی ادمین ارسال شد.</Alert></div> : null}
      {mutation.isError ? <div className="mt-4"><Alert variant="danger">{isApiError(mutation.error) ? mutation.error.message : "ثبت پروفایل انجام نشد."}</Alert></div> : null}
    </form>
  );
}
