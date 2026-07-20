import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type FormEvent } from "react";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { Breadcrumb } from "@/components/Breadcrumb";
import { AccountGuard } from "@/components/account/AccountGuard";
import {
  Alert,
  Button,
  Dialog,
  EmptyState,
  FormSummary,
  TextField,
  TextareaField,
  useToast,
} from "@/components/system";
import {
  addressesQueryOptions,
  createAddress,
  deleteAddress,
  logout,
  updateAddress,
  updateCurrentUser,
} from "@/lib/api/identity";
import { queryKeys } from "@/lib/api/query-keys";
import { isApiError } from "@/lib/api/client";
import type { Address, AddressInput, AuthUser } from "@/lib/api/contracts";
import { loadProfile, type TasteProfile } from "@/lib/quiz-logic";
import { absoluteUrl } from "@/config/site";

export const Route = createFileRoute("/profile")({
  head: () => ({
    meta: [
      { title: "حساب کاربری من | رستا" },
      { name: "description", content: "مدیریت مشخصات، آدرس‌ها، سلیقه قهوه و سفارش‌های رستا." },
      { name: "robots", content: "noindex,nofollow" },
    ],
    links: [{ rel: "canonical", href: absoluteUrl("/profile") }],
  }),
  component: ProfilePage,
});

const emptyAddress: AddressInput = {
  title: "",
  recipientName: "",
  recipientMobile: "",
  province: "",
  city: "",
  addressLine: "",
  postalCode: "",
  isDefault: false,
};

function addressToInput(address: Address): AddressInput {
  return {
    title: address.title ?? "",
    recipientName: address.recipientName,
    recipientMobile: address.recipientMobile,
    province: address.province,
    city: address.city,
    addressLine: address.addressLine,
    postalCode: address.postalCode ?? "",
    isDefault: address.isDefault,
  };
}

function ProfilePage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-5xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "حساب من" }]} />
        <AccountGuard>{(user) => <ProfileContent user={user} />}</AccountGuard>
      </main>
      <Footer />
    </>
  );
}

function ProfileContent({ user }: { user: AuthUser }) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { pushToast } = useToast();
  const addressesQuery = useQuery(addressesQueryOptions());
  const [tasteProfile, setTasteProfile] = useState<TasteProfile | null>(null);
  const [profileOpen, setProfileOpen] = useState(false);
  const [addressOpen, setAddressOpen] = useState(false);
  const [editingAddress, setEditingAddress] = useState<Address | null>(null);
  const [profileForm, setProfileForm] = useState({ name: user.name ?? "", email: user.email ?? "" });
  const [addressForm, setAddressForm] = useState<AddressInput>(emptyAddress);
  const [profileErrors, setProfileErrors] = useState<Array<{ fieldId: string; message: string }>>([]);
  const [addressErrors, setAddressErrors] = useState<Array<{ fieldId: string; message: string }>>([]);

  useEffect(() => {
    setTasteProfile(loadProfile());
  }, []);

  useEffect(() => {
    setProfileForm({ name: user.name ?? "", email: user.email ?? "" });
  }, [user.email, user.name]);

  const profileMutation = useMutation({
    mutationFn: updateCurrentUser,
    onSuccess: (updated) => {
      queryClient.setQueryData(queryKeys.auth.me(), updated);
      setProfileOpen(false);
      pushToast({ variant: "success", title: "مشخصات ذخیره شد" });
    },
  });

  const saveAddressMutation = useMutation({
    mutationFn: async () =>
      editingAddress
        ? updateAddress(editingAddress.id, addressForm)
        : createAddress(addressForm),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.profile.addresses() });
      setAddressOpen(false);
      setEditingAddress(null);
      setAddressForm(emptyAddress);
      pushToast({ variant: "success", title: editingAddress ? "آدرس ویرایش شد" : "آدرس افزوده شد" });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteAddress,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.profile.addresses() });
      pushToast({ variant: "success", title: "آدرس حذف شد" });
    },
  });

  const logoutMutation = useMutation({
    mutationFn: logout,
    onSettled: async () => {
      queryClient.removeQueries({ queryKey: queryKeys.auth.all });
      queryClient.removeQueries({ queryKey: queryKeys.profile.all });
      queryClient.removeQueries({ queryKey: queryKeys.orders.all });
      await navigate({ to: "/", replace: true });
    },
  });

  const submitProfile = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const errors: Array<{ fieldId: string; message: string }> = [];
    if (profileForm.name.trim().length > 0 && profileForm.name.trim().length < 2) {
      errors.push({ fieldId: "profile-name", message: "نام باید حداقل دو نویسه باشد." });
    }
    if (profileForm.email && !/^\S+@\S+\.\S+$/.test(profileForm.email)) {
      errors.push({ fieldId: "profile-email", message: "ایمیل معتبر وارد کنید." });
    }
    setProfileErrors(errors);
    if (errors.length) return;
    profileMutation.mutate({
      name: profileForm.name.trim() || null,
      email: profileForm.email.trim() || null,
    });
  };

  const submitAddress = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const errors: Array<{ fieldId: string; message: string }> = [];
    if (!addressForm.recipientName.trim()) errors.push({ fieldId: "address-recipient", message: "نام گیرنده را وارد کنید." });
    if (!/^09\d{9}$/.test(addressForm.recipientMobile.replace(/\s/g, ""))) errors.push({ fieldId: "address-mobile", message: "شماره موبایل گیرنده معتبر نیست." });
    if (!addressForm.province.trim()) errors.push({ fieldId: "address-province", message: "استان را وارد کنید." });
    if (!addressForm.city.trim()) errors.push({ fieldId: "address-city", message: "شهر را وارد کنید." });
    if (addressForm.addressLine.trim().length < 10) errors.push({ fieldId: "address-line", message: "نشانی کامل‌تر وارد کنید." });
    setAddressErrors(errors);
    if (errors.length) return;
    saveAddressMutation.mutate();
  };

  const openNewAddress = () => {
    setEditingAddress(null);
    setAddressForm({ ...emptyAddress, recipientName: user.name ?? "", recipientMobile: user.mobile });
    setAddressErrors([]);
    setAddressOpen(true);
  };

  const openEditAddress = (address: Address) => {
    setEditingAddress(address);
    setAddressForm(addressToInput(address));
    setAddressErrors([]);
    setAddressOpen(true);
  };

  const addresses = addressesQuery.data ?? [];

  return (
    <>
      <header className="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">ACCOUNT</p>
          <h1 className="mt-2 text-3xl font-bold">حساب کاربری</h1>
          <p className="mt-2 text-sm text-[color:var(--light)]">نشست امن مبتنی بر Cookie؛ بدون ذخیره Token در مرورگر.</p>
        </div>
        <Button variant="outline" onClick={() => setProfileOpen(true)}>ویرایش مشخصات</Button>
      </header>

      <div className="mt-8 grid gap-5 md:grid-cols-2">
        <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <div className="flex items-center gap-4">
            <div className="grid size-14 place-items-center rounded-full bg-[color:var(--roast)] text-xl font-bold text-[color:var(--night)]">
              {(user.name || "ر").slice(0, 1)}
            </div>
            <div>
              <h2 className="font-bold">{user.name || "کاربر رستا"}</h2>
              <p dir="ltr" className="mt-1 text-start font-mono text-xs text-[color:var(--light)]">{user.mobile}</p>
              {user.email ? <p dir="ltr" className="mt-1 text-start text-xs text-[color:var(--light)]">{user.email}</p> : null}
            </div>
          </div>
        </section>

        <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <h2 className="font-bold">پروفایل سلیقه</h2>
          {tasteProfile ? (
            <>
              <ul className="mt-3 space-y-1 text-sm leading-7 text-[color:var(--light)]">
                {tasteProfile.roast ? <li>سطح رست: {tasteProfile.roast}</li> : null}
                {tasteProfile.brewMethod ? <li>روش دم‌آوری: {tasteProfile.brewMethod}</li> : null}
                {tasteProfile.flavors.length ? <li>طعم‌ها: {tasteProfile.flavors.join("، ")}</li> : null}
              </ul>
              <p className="mt-3 text-[11px] leading-6 text-[color:var(--light)]">این نتیجه فعلاً فقط برای UX محلی کوییز نگه‌داری می‌شود؛ همگام‌سازی Backend در فاز کوییز انجام خواهد شد.</p>
              <Link to="/quiz" className="mt-3 inline-block text-xs font-bold text-[color:var(--roast)] underline">ویرایش سلیقه</Link>
            </>
          ) : (
            <EmptyState
              title="سلیقه‌ای ثبت نشده"
              description="با چند سؤال، دانه‌های مناسب روش دم‌آوری خود را پیدا کنید."
              action={<Link to="/quiz" className="inline-flex rounded-xl bg-[color:var(--roast)] px-4 py-2 text-xs font-bold text-[color:var(--night)]">شروع کوییز</Link>}
            />
          )}
        </section>

        <section className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 md:col-span-2">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 className="font-bold">آدرس‌های من</h2>
              <p className="mt-1 text-xs text-[color:var(--light)]">آدرس‌ها از حساب واقعی API دریافت می‌شوند.</p>
            </div>
            <Button onClick={openNewAddress}>افزودن آدرس</Button>
          </div>

          {addressesQuery.isPending ? (
            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              <div className="h-36 animate-pulse rounded-xl bg-[color:var(--night)]" />
              <div className="h-36 animate-pulse rounded-xl bg-[color:var(--night)]" />
            </div>
          ) : addressesQuery.isError ? (
            <div className="mt-5">
              <Alert variant="danger" title="آدرس‌ها دریافت نشدند">
                {isApiError(addressesQuery.error) ? addressesQuery.error.message : "ارتباط با API برقرار نشد."}
              </Alert>
              <Button variant="outline" className="mt-3" onClick={() => addressesQuery.refetch()}>تلاش مجدد</Button>
            </div>
          ) : addresses.length === 0 ? (
            <div className="mt-5"><EmptyState title="هنوز آدرسی ثبت نشده" description="برای Checkout سریع‌تر، نشانی گیرنده را ذخیره کنید." /></div>
          ) : (
            <div className="mt-5 grid gap-4 sm:grid-cols-2">
              {addresses.map((address) => (
                <article key={address.id} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4">
                  <div className="flex items-center justify-between gap-3">
                    <h3 className="font-bold">{address.title || "آدرس"}</h3>
                    {address.isDefault ? <span className="rounded-full bg-[color:var(--roast)] px-2 py-0.5 text-[10px] font-bold text-[color:var(--night)]">پیش‌فرض</span> : null}
                  </div>
                  <p className="mt-3 text-sm leading-7 text-[color:var(--light)]">{address.province}، {address.city}، {address.addressLine}</p>
                  <p className="mt-2 text-xs text-[color:var(--light)]">گیرنده: {address.recipientName} · {address.recipientMobile}</p>
                  <div className="mt-4 flex gap-3">
                    <button type="button" onClick={() => openEditAddress(address)} className="text-xs font-bold text-[color:var(--roast)]">ویرایش</button>
                    <button
                      type="button"
                      disabled={deleteMutation.isPending}
                      onClick={() => {
                        if (window.confirm("این آدرس حذف شود؟")) deleteMutation.mutate(address.id);
                      }}
                      className="text-xs font-bold text-red-300 disabled:opacity-50"
                    >
                      حذف
                    </button>
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>

        <nav className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 md:col-span-2" aria-label="دسترسی‌های حساب">
          <ul className="divide-y divide-[color:var(--mid)] text-sm">
            <li><Link to="/orders" className="flex items-center justify-between py-4 font-bold hover:text-[color:var(--roast)]"><span>سفارش‌های من</span><span aria-hidden>←</span></Link></li>
            <li><Link to="/quiz" className="flex items-center justify-between py-4 font-bold hover:text-[color:var(--roast)]"><span>کوییز سلیقه قهوه</span><span aria-hidden>←</span></Link></li>
            <li>
              <button type="button" disabled={logoutMutation.isPending} onClick={() => logoutMutation.mutate()} className="w-full py-4 text-start font-bold text-red-300 disabled:opacity-50">
                {logoutMutation.isPending ? "در حال خروج…" : "خروج از حساب"}
              </button>
            </li>
          </ul>
        </nav>
      </div>

      <Dialog open={profileOpen} onOpenChange={setProfileOpen} title="ویرایش مشخصات" description="شماره موبایل از مسیر تأیید جداگانه تغییر می‌کند.">
        <form onSubmit={submitProfile} className="grid gap-4" noValidate>
          <FormSummary errors={profileErrors} />
          {profileMutation.isError ? <Alert variant="danger">{isApiError(profileMutation.error) ? profileMutation.error.message : "ذخیره مشخصات انجام نشد."}</Alert> : null}
          <TextField id="profile-name" label="نام نمایشی" value={profileForm.name} onChange={(event) => setProfileForm((value) => ({ ...value, name: event.target.value }))} />
          <TextField id="profile-email" label="ایمیل" type="email" dir="ltr" value={profileForm.email} onChange={(event) => setProfileForm((value) => ({ ...value, email: event.target.value }))} />
          <Button type="submit" loading={profileMutation.isPending}>ذخیره مشخصات</Button>
        </form>
      </Dialog>

      <Dialog open={addressOpen} onOpenChange={setAddressOpen} title={editingAddress ? "ویرایش آدرس" : "افزودن آدرس"} description="اطلاعات گیرنده برای سفارش و ارسال استفاده می‌شود.">
        <form onSubmit={submitAddress} className="grid gap-4" noValidate>
          <FormSummary errors={addressErrors} />
          {saveAddressMutation.isError ? <Alert variant="danger">{isApiError(saveAddressMutation.error) ? saveAddressMutation.error.message : "ذخیره آدرس انجام نشد."}</Alert> : null}
          <TextField label="عنوان آدرس" placeholder="خانه، محل کار…" value={addressForm.title ?? ""} onChange={(event) => setAddressForm((value) => ({ ...value, title: event.target.value }))} />
          <TextField id="address-recipient" label="نام گیرنده" value={addressForm.recipientName} onChange={(event) => setAddressForm((value) => ({ ...value, recipientName: event.target.value }))} required />
          <TextField id="address-mobile" label="موبایل گیرنده" dir="ltr" inputMode="tel" value={addressForm.recipientMobile} onChange={(event) => setAddressForm((value) => ({ ...value, recipientMobile: event.target.value }))} required />
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField id="address-province" label="استان" value={addressForm.province} onChange={(event) => setAddressForm((value) => ({ ...value, province: event.target.value }))} required />
            <TextField id="address-city" label="شهر" value={addressForm.city} onChange={(event) => setAddressForm((value) => ({ ...value, city: event.target.value }))} required />
          </div>
          <TextareaField id="address-line" label="نشانی کامل" value={addressForm.addressLine} onChange={(event) => setAddressForm((value) => ({ ...value, addressLine: event.target.value }))} required />
          <TextField label="کد پستی" dir="ltr" inputMode="numeric" value={addressForm.postalCode ?? ""} onChange={(event) => setAddressForm((value) => ({ ...value, postalCode: event.target.value }))} />
          <label className="flex items-center gap-3 text-sm"><input type="checkbox" checked={addressForm.isDefault} onChange={(event) => setAddressForm((value) => ({ ...value, isDefault: event.target.checked }))} className="size-4 accent-[color:var(--roast)]" />آدرس پیش‌فرض من</label>
          <Button type="submit" loading={saveAddressMutation.isPending}>{editingAddress ? "ذخیره ویرایش" : "افزودن آدرس"}</Button>
        </form>
      </Dialog>
    </>
  );
}
