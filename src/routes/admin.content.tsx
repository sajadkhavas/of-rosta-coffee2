import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import { useMemo, useState, type FormEvent, type ReactNode } from "react";
import { EditorialContentDialog } from "@/components/admin/EditorialContentDialog";
import { AccountGuard } from "@/components/account/AccountGuard";
import { Breadcrumb } from "@/components/Breadcrumb";
import { Footer } from "@/components/Footer";
import { Navbar } from "@/components/Navbar";
import { Alert, Button } from "@/components/system";
import {
  adminContentQueryOptions,
  contentAuthorsQueryOptions,
  createContentAuthor,
  createContentEntry,
  createSeoRedirect,
  seoRedirectsQueryOptions,
  setContentStatus,
  type AdminContentDetail,
  type AdminContentStatus,
  type AdminContentType,
  type AdminSchemaType,
} from "@/lib/api/admin-content";
import { isApiError } from "@/lib/api/client";
import type { ContentBlock } from "@/lib/api/content";

export const Route = createFileRoute("/admin/content")({
  head: () => ({
    meta: [
      { title: "مدیریت محتوا و سئو | ادمین رستا" },
      { name: "robots", content: "noindex,nofollow" },
    ],
  }),
  component: AdminContentPage,
});

const fieldClass =
  "w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-2.5 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

const STATUS_LABELS: Record<AdminContentStatus, string> = {
  draft: "پیش‌نویس",
  review: "در انتظار بررسی",
  published: "منتشرشده",
  archived: "بایگانی",
};

const TYPE_LABELS: Record<AdminContentType, string> = {
  article: "مقاله",
  guide: "راهنما",
  comparison: "مقایسه",
  landing: "لندینگ",
  origin: "خاستگاه",
  brew_method: "روش دم‌آوری",
  taste: "طعم",
  collection: "کالکشن",
};

const TYPE_DEFAULTS: Record<AdminContentType, { prefix: string; schema: AdminSchemaType }> = {
  article: { prefix: "/blog/", schema: "BlogPosting" },
  guide: { prefix: "/guides/", schema: "BlogPosting" },
  comparison: { prefix: "/compare/", schema: "Article" },
  landing: { prefix: "/", schema: "WebPage" },
  origin: { prefix: "/origins/", schema: "CollectionPage" },
  brew_method: { prefix: "/brew/", schema: "Article" },
  taste: { prefix: "/tastes/", schema: "CollectionPage" },
  collection: { prefix: "/collections/", schema: "CollectionPage" },
};

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="grid gap-2 text-sm font-bold text-[color:var(--steam)]">
      {label}
      {children}
    </label>
  );
}

function errorMessage(error: unknown): string {
  return isApiError(error)
    ? error.message
    : "درخواست انجام نشد. اتصال سرویس و اطلاعات ورودی را بررسی کنید.";
}

function splitComma(value: string): string[] {
  return [
    ...new Set(
      value
        .split(/[،,]/)
        .map((item) => item.trim())
        .filter(Boolean),
    ),
  ];
}

function AdminContentPage() {
  return (
    <>
      <Navbar />
      <main className="mx-auto max-w-7xl px-4 py-8">
        <Breadcrumb items={[{ label: "خانه", to: "/" }, { label: "مدیریت محتوا و سئو" }]} />
        <AccountGuard>
          {(user) =>
            user.roles.includes("administrator") ? (
              <AdminContentDashboard />
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

function AdminContentDashboard() {
  const queryClient = useQueryClient();
  const contentQuery = useQuery(adminContentQueryOptions());
  const authorsQuery = useQuery(contentAuthorsQueryOptions());
  const redirectsQuery = useQuery(seoRedirectsQueryOptions());
  const [selectedEntryId, setSelectedEntryId] = useState<string | null>(null);

  const [authorForm, setAuthorForm] = useState({
    name: "",
    slug: "",
    bio: "",
    credentials: "",
  });
  const [contentForm, setContentForm] = useState({
    authorId: "",
    type: "guide" as AdminContentType,
    title: "",
    slug: "",
    canonicalPath: "/guides/",
    excerpt: "",
    paragraphs: "",
    seoTitle: "",
    seoDescription: "",
    keywords: "",
    robotsIndex: true,
    schemaType: "BlogPosting" as AdminSchemaType,
  });
  const [redirectForm, setRedirectForm] = useState({
    sourcePath: "",
    destinationPath: "",
    statusCode: 301 as 301 | 308,
  });

  const authorMutation = useMutation({
    mutationFn: createContentAuthor,
    onSuccess: async () => {
      setAuthorForm({ name: "", slug: "", bio: "", credentials: "" });
      await queryClient.invalidateQueries({
        queryKey: ["admin", "content-authors"],
      });
    },
  });

  const contentMutation = useMutation({
    mutationFn: createContentEntry,
    onSuccess: async (entry) => {
      const defaults = TYPE_DEFAULTS[contentForm.type];
      setContentForm((current) => ({
        ...current,
        title: "",
        slug: "",
        canonicalPath: defaults.prefix,
        excerpt: "",
        paragraphs: "",
        seoTitle: "",
        seoDescription: "",
        keywords: "",
      }));
      await queryClient.invalidateQueries({ queryKey: ["admin", "content"] });
      setSelectedEntryId(entry.id);
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: string; status: AdminContentStatus }) =>
      setContentStatus(id, status),
    onSuccess: async (entry) => {
      queryClient.setQueryData(["admin", "content", entry.id], entry);
      await queryClient.invalidateQueries({ queryKey: ["admin", "content"] });
    },
  });

  const redirectMutation = useMutation({
    mutationFn: createSeoRedirect,
    onSuccess: async () => {
      setRedirectForm({
        sourcePath: "",
        destinationPath: "",
        statusCode: 301,
      });
      await queryClient.invalidateQueries({
        queryKey: ["admin", "seo-redirects"],
      });
    },
  });

  const paragraphBlocks = useMemo<ContentBlock[]>(() => {
    return contentForm.paragraphs
      .split(/\n\s*\n/)
      .map((paragraph) => paragraph.trim())
      .filter(Boolean)
      .map((text) => ({ type: "paragraph", text }));
  }, [contentForm.paragraphs]);

  const submitAuthor = (event: FormEvent) => {
    event.preventDefault();
    authorMutation.mutate({
      name: authorForm.name.trim(),
      slug: authorForm.slug.trim(),
      bio: authorForm.bio.trim() || undefined,
      credentials: splitComma(authorForm.credentials),
    });
  };

  const submitContent = (event: FormEvent) => {
    event.preventDefault();
    if (paragraphBlocks.length < 2) return;

    contentMutation.mutate({
      author_id: contentForm.authorId,
      type: contentForm.type,
      title: contentForm.title.trim(),
      slug: contentForm.slug.trim(),
      canonical_path: contentForm.canonicalPath.trim(),
      excerpt: contentForm.excerpt.trim(),
      body: paragraphBlocks,
      seo_title: contentForm.seoTitle.trim(),
      seo_description: contentForm.seoDescription.trim(),
      robots_index: contentForm.robotsIndex,
      robots_follow: true,
      schema_type: contentForm.schemaType,
      keywords: splitComma(contentForm.keywords),
      relations: [],
    });
  };

  const submitRedirect = (event: FormEvent) => {
    event.preventDefault();
    redirectMutation.mutate({
      source_path: redirectForm.sourcePath.trim(),
      destination_path: redirectForm.destinationPath.trim(),
      status_code: redirectForm.statusCode,
    });
  };

  const changeContentType = (type: AdminContentType) => {
    const defaults = TYPE_DEFAULTS[type];
    setContentForm((current) => ({
      ...current,
      type,
      canonicalPath:
        current.canonicalPath === TYPE_DEFAULTS[current.type].prefix
          ? defaults.prefix
          : current.canonicalPath,
      schemaType: defaults.schema,
    }));
  };

  const initialError = contentQuery.error || authorsQuery.error || redirectsQuery.error;

  const handleSaved = (entry: AdminContentDetail) => {
    queryClient.setQueryData(["admin", "content", entry.id], entry);
  };

  return (
    <section className="mt-8 space-y-10">
      <header>
        <p className="text-xs font-bold text-[color:var(--roast)]">SEO OPERATIONS</p>
        <h1 className="mt-2 text-3xl font-bold text-[color:var(--steam)]">
          مدیریت محتوا، انتشار و Redirect
        </h1>
        <p className="mt-3 max-w-3xl text-sm leading-8 text-[color:var(--light)]">
          محتوای جدید ابتدا Draft است. ویرایشگر کامل از Blockهای امن، روابط داخلی، Preview مشترک و
          جلوگیری از بازنویسی هم‌زمان استفاده می‌کند.
        </p>
      </header>

      {initialError ? (
        <Alert variant="danger" title="داده‌های ادمین دریافت نشد">
          {errorMessage(initialError)}
        </Alert>
      ) : null}

      <div className="grid gap-6 xl:grid-cols-3">
        <form
          onSubmit={submitAuthor}
          className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
        >
          <h2 className="text-lg font-bold">نویسنده جدید</h2>
          <div className="mt-4 space-y-3">
            <Field label="نام نویسنده">
              <input
                required
                value={authorForm.name}
                onChange={(event) =>
                  setAuthorForm((current) => ({
                    ...current,
                    name: event.target.value,
                  }))
                }
                className={fieldClass}
              />
            </Field>
            <Field label="Slug نویسنده">
              <input
                required
                dir="ltr"
                value={authorForm.slug}
                onChange={(event) =>
                  setAuthorForm((current) => ({
                    ...current,
                    slug: event.target.value,
                  }))
                }
                className={`${fieldClass} text-left`}
              />
            </Field>
            <Field label="زندگی‌نامه و حوزه تخصص">
              <textarea
                value={authorForm.bio}
                onChange={(event) =>
                  setAuthorForm((current) => ({
                    ...current,
                    bio: event.target.value,
                  }))
                }
                rows={4}
                className={fieldClass}
              />
            </Field>
            <Field label="تخصص‌ها با ویرگول جدا شوند">
              <input
                value={authorForm.credentials}
                onChange={(event) =>
                  setAuthorForm((current) => ({
                    ...current,
                    credentials: event.target.value,
                  }))
                }
                className={fieldClass}
              />
            </Field>
          </div>
          {authorMutation.isError ? (
            <p className="mt-3 text-xs text-red-300">{errorMessage(authorMutation.error)}</p>
          ) : null}
          <Button type="submit" className="mt-4 w-full" loading={authorMutation.isPending}>
            ثبت نویسنده
          </Button>
        </form>

        <form
          onSubmit={submitRedirect}
          className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5"
        >
          <h2 className="text-lg font-bold">Redirect دائمی</h2>
          <div className="mt-4 space-y-3">
            <Field label="مسیر قدیمی">
              <input
                required
                dir="ltr"
                value={redirectForm.sourcePath}
                onChange={(event) =>
                  setRedirectForm((current) => ({
                    ...current,
                    sourcePath: event.target.value,
                  }))
                }
                placeholder="/old-path"
                className={`${fieldClass} text-left`}
              />
            </Field>
            <Field label="مسیر مقصد">
              <input
                required
                dir="ltr"
                value={redirectForm.destinationPath}
                onChange={(event) =>
                  setRedirectForm((current) => ({
                    ...current,
                    destinationPath: event.target.value,
                  }))
                }
                placeholder="/guides/new-path"
                className={`${fieldClass} text-left`}
              />
            </Field>
            <Field label="کد Redirect">
              <select
                value={redirectForm.statusCode}
                onChange={(event) =>
                  setRedirectForm((current) => ({
                    ...current,
                    statusCode: Number(event.target.value) as 301 | 308,
                  }))
                }
                className={fieldClass}
              >
                <option value={301}>301</option>
                <option value={308}>308</option>
              </select>
            </Field>
          </div>
          {redirectMutation.isError ? (
            <p className="mt-3 text-xs text-red-300">{errorMessage(redirectMutation.error)}</p>
          ) : null}
          <Button type="submit" className="mt-4 w-full" loading={redirectMutation.isPending}>
            ثبت Redirect
          </Button>
        </form>

        <div className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5">
          <h2 className="text-lg font-bold">خلاصه عملیات</h2>
          <dl className="mt-5 space-y-4 text-sm">
            {[
              ["محتوا", contentQuery.data?.length ?? 0],
              ["نویسندگان", authorsQuery.data?.length ?? 0],
              ["Redirectها", redirectsQuery.data?.length ?? 0],
            ].map(([label, count]) => (
              <div key={String(label)} className="flex justify-between">
                <dt className="text-[color:var(--light)]">{label}</dt>
                <dd className="font-bold text-[color:var(--roast)]">
                  {Number(count).toLocaleString("fa-IR")}
                </dd>
              </div>
            ))}
          </dl>
          <div className="mt-6 max-h-48 space-y-2 overflow-auto text-xs text-[color:var(--light)]">
            {redirectsQuery.data?.map((redirect) => (
              <div
                key={redirect.id}
                className="rounded-lg border border-[color:var(--mid)] p-2"
                dir="ltr"
              >
                {redirect.source_path} → {redirect.destination_path} ({redirect.hits})
              </div>
            ))}
          </div>
        </div>
      </div>

      <form
        onSubmit={submitContent}
        className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 sm:p-6"
      >
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 className="text-xl font-bold">ایجاد Draft سریع</h2>
            <p className="mt-2 text-xs leading-6 text-[color:var(--light)]">
              Draft اولیه با دو پاراگراف ساخته می‌شود؛ پس از ساخت، ویرایشگر کامل خودکار باز خواهد
              شد.
            </p>
          </div>
          <span className="rounded-full border border-[color:var(--mid)] px-3 py-1 text-xs text-[color:var(--roast)]">
            {paragraphBlocks.length.toLocaleString("fa-IR")} بلوک اولیه
          </span>
        </div>
        <div className="mt-5 grid gap-3 md:grid-cols-2">
          <Field label="نوع محتوا">
            <select
              value={contentForm.type}
              onChange={(event) => changeContentType(event.target.value as AdminContentType)}
              className={fieldClass}
            >
              {(Object.keys(TYPE_LABELS) as AdminContentType[]).map((type) => (
                <option key={type} value={type}>
                  {TYPE_LABELS[type]}
                </option>
              ))}
            </select>
          </Field>
          <Field label="نویسنده">
            <select
              required
              value={contentForm.authorId}
              onChange={(event) =>
                setContentForm((current) => ({
                  ...current,
                  authorId: event.target.value,
                }))
              }
              className={fieldClass}
            >
              <option value="">انتخاب نویسنده</option>
              {authorsQuery.data
                ?.filter((author) => author.is_active)
                .map((author) => (
                  <option key={author.id} value={author.id}>
                    {author.name}
                  </option>
                ))}
            </select>
          </Field>
          <Field label="عنوان صفحه">
            <input
              required
              value={contentForm.title}
              onChange={(event) =>
                setContentForm((current) => ({
                  ...current,
                  title: event.target.value,
                }))
              }
              className={fieldClass}
            />
          </Field>
          <Field label="Slug">
            <input
              required
              dir="ltr"
              value={contentForm.slug}
              onChange={(event) =>
                setContentForm((current) => ({
                  ...current,
                  slug: event.target.value,
                }))
              }
              className={`${fieldClass} text-left`}
            />
          </Field>
          <Field label="Canonical path">
            <input
              required
              dir="ltr"
              value={contentForm.canonicalPath}
              onChange={(event) =>
                setContentForm((current) => ({
                  ...current,
                  canonicalPath: event.target.value,
                }))
              }
              className={`${fieldClass} text-left`}
            />
          </Field>
          <Field label="عنوان SEO">
            <input
              required
              value={contentForm.seoTitle}
              onChange={(event) =>
                setContentForm((current) => ({
                  ...current,
                  seoTitle: event.target.value,
                }))
              }
              className={fieldClass}
            />
          </Field>
          <div className="md:col-span-2">
            <Field label="خلاصه صفحه">
              <textarea
                required
                value={contentForm.excerpt}
                onChange={(event) =>
                  setContentForm((current) => ({
                    ...current,
                    excerpt: event.target.value,
                  }))
                }
                rows={3}
                className={fieldClass}
              />
            </Field>
          </div>
          <div className="md:col-span-2">
            <Field label="پاراگراف‌های اولیه؛ با خط خالی جدا شوند">
              <textarea
                required
                value={contentForm.paragraphs}
                onChange={(event) =>
                  setContentForm((current) => ({
                    ...current,
                    paragraphs: event.target.value,
                  }))
                }
                placeholder={"پاراگراف اول…\n\nپاراگراف دوم…"}
                rows={7}
                className={fieldClass}
              />
            </Field>
          </div>
          <div className="md:col-span-2">
            <Field label="توضیح SEO">
              <textarea
                required
                value={contentForm.seoDescription}
                onChange={(event) =>
                  setContentForm((current) => ({
                    ...current,
                    seoDescription: event.target.value,
                  }))
                }
                rows={3}
                className={fieldClass}
              />
            </Field>
          </div>
          <Field label="کلمات کلیدی">
            <input
              value={contentForm.keywords}
              onChange={(event) =>
                setContentForm((current) => ({
                  ...current,
                  keywords: event.target.value,
                }))
              }
              className={fieldClass}
            />
          </Field>
          <label className="flex items-center gap-2 rounded-xl border border-[color:var(--mid)] p-3 text-sm text-[color:var(--light)]">
            <input
              type="checkbox"
              checked={contentForm.robotsIndex}
              onChange={(event) =>
                setContentForm((current) => ({
                  ...current,
                  robotsIndex: event.target.checked,
                }))
              }
            />
            پس از Review و انتشار وارد Sitemap شود
          </label>
        </div>
        {contentMutation.isError ? (
          <p className="mt-3 text-sm text-red-300">{errorMessage(contentMutation.error)}</p>
        ) : null}
        <Button
          type="submit"
          className="mt-5"
          loading={contentMutation.isPending}
          disabled={paragraphBlocks.length < 2}
        >
          ایجاد Draft و بازکردن ویرایشگر
        </Button>
      </form>

      <section>
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 className="text-2xl font-bold">صف محتوا و انتشار</h2>
            <p className="mt-2 text-xs text-[color:var(--light)]">
              ویرایش محتوای Published آن را خودکار به Review برمی‌گرداند.
            </p>
          </div>
          <Button variant="outline" onClick={() => contentQuery.refetch()}>
            تازه‌سازی
          </Button>
        </div>
        <div className="mt-5 space-y-3">
          {contentQuery.isPending ? (
            <p className="text-sm text-[color:var(--light)]">در حال دریافت محتوا…</p>
          ) : contentQuery.data?.length ? (
            contentQuery.data.map((entry) => (
              <article
                key={entry.id}
                className="grid gap-4 rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-5 lg:grid-cols-[1fr_auto] lg:items-center"
              >
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="font-bold text-[color:var(--steam)]">{entry.title}</h3>
                    <span className="rounded-full border border-[color:var(--mid)] px-2 py-0.5 text-[10px] text-[color:var(--roast)]">
                      {STATUS_LABELS[entry.status]}
                    </span>
                    <span className="rounded-full border border-[color:var(--mid)] px-2 py-0.5 text-[10px]">
                      {TYPE_LABELS[entry.type]}
                    </span>
                    <span className="rounded-full border border-[color:var(--mid)] px-2 py-0.5 text-[10px]">
                      {entry.seo.robots_index ? "index" : "noindex"}
                    </span>
                  </div>
                  <p className="mt-2 break-all text-xs text-[color:var(--light)]" dir="ltr">
                    {entry.canonical_path}
                  </p>
                  <p className="mt-2 text-xs text-[color:var(--light)]">
                    نویسنده: {entry.author?.name ?? "تعیین نشده"}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button variant="secondary" onClick={() => setSelectedEntryId(entry.id)}>
                    ویرایش و Preview
                  </Button>
                  {entry.status === "draft" ? (
                    <Button
                      variant="outline"
                      disabled={statusMutation.isPending}
                      onClick={() => statusMutation.mutate({ id: entry.id, status: "review" })}
                    >
                      ارسال برای بررسی
                    </Button>
                  ) : null}
                  {entry.status === "review" ? (
                    <Button
                      disabled={statusMutation.isPending}
                      onClick={() =>
                        statusMutation.mutate({
                          id: entry.id,
                          status: "published",
                        })
                      }
                    >
                      انتشار
                    </Button>
                  ) : null}
                  {entry.status !== "archived" ? (
                    <Button
                      variant="ghost"
                      disabled={statusMutation.isPending}
                      onClick={() =>
                        statusMutation.mutate({
                          id: entry.id,
                          status: "archived",
                        })
                      }
                    >
                      بایگانی
                    </Button>
                  ) : null}
                </div>
              </article>
            ))
          ) : (
            <p className="rounded-2xl border border-dashed border-[color:var(--mid)] p-8 text-center text-sm text-[color:var(--light)]">
              هنوز محتوایی ایجاد نشده است.
            </p>
          )}
        </div>
        {statusMutation.isError ? (
          <div className="mt-4">
            <Alert variant="danger" title="تغییر وضعیت انجام نشد">
              {errorMessage(statusMutation.error)}
            </Alert>
          </div>
        ) : null}
      </section>

      <EditorialContentDialog
        entryId={selectedEntryId}
        authors={authorsQuery.data ?? []}
        onClose={() => setSelectedEntryId(null)}
        onSaved={handleSaved}
      />
    </section>
  );
}
