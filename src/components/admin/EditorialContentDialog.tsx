import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useRef, useState, type FormEvent } from "react";
import { ContentRelationEditor } from "@/components/admin/ContentRelationEditor";
import { StructuredBlockEditor } from "@/components/admin/StructuredBlockEditor";
import { StructuredContentBlocks } from "@/components/content/StructuredContentBlocks";
import { Alert, Button } from "@/components/system";
import {
  adminContentDetailQueryOptions,
  updateContentEntry,
  type AdminContentAuthor,
  type AdminContentDetail,
  type AdminContentType,
  type AdminSchemaType,
  type ContentRelationInput,
  type UpdateContentEntryInput,
} from "@/lib/api/admin-content";
import { isApiError } from "@/lib/api/client";
import { contentBlockSchema, type ContentBlock } from "@/lib/api/content";

const inputClass =
  "w-full rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-3 py-2.5 text-sm text-[color:var(--steam)] outline-none focus:border-[color:var(--roast)]";

const typeLabels: Record<AdminContentType, string> = {
  article: "مقاله",
  guide: "راهنما",
  comparison: "مقایسه",
  landing: "لندینگ",
  origin: "خاستگاه",
  brew_method: "روش دم‌آوری",
  taste: "طعم",
  collection: "کالکشن",
};

const schemaLabels: Record<AdminSchemaType, string> = {
  Article: "Article",
  BlogPosting: "BlogPosting",
  CollectionPage: "CollectionPage",
  FAQPage: "FAQPage",
  WebPage: "WebPage",
};

interface EditorState {
  expectedHash: string;
  authorId: string;
  type: AdminContentType;
  title: string;
  slug: string;
  canonicalPath: string;
  excerpt: string;
  blocks: ContentBlock[];
  seoTitle: string;
  seoDescription: string;
  ogTitle: string;
  ogDescription: string;
  ogMediaUrl: string;
  robotsIndex: boolean;
  robotsFollow: boolean;
  schemaType: AdminSchemaType;
  keywords: string;
  relations: ContentRelationInput[];
}

function stateFromEntry(entry: AdminContentDetail): EditorState {
  return {
    expectedHash: entry.content_hash,
    authorId: entry.author?.id ?? "",
    type: entry.type,
    title: entry.title,
    slug: entry.slug,
    canonicalPath: entry.canonical_path,
    excerpt: entry.excerpt ?? "",
    blocks: entry.body.map((block) => structuredClone(block)),
    seoTitle: entry.seo.title,
    seoDescription: entry.seo.description ?? "",
    ogTitle: entry.seo.og_title,
    ogDescription: entry.seo.og_description ?? "",
    ogMediaUrl: entry.seo.og_media_url ?? "",
    robotsIndex: entry.seo.robots_index,
    robotsFollow: entry.seo.robots_follow,
    schemaType: entry.seo.schema_type,
    keywords: entry.keywords.join("، "),
    relations: entry.relations.map((relation) => ({
      relation_type: relation.relation_type,
      target_type: relation.target_type,
      target_key: relation.target_key,
      anchor_text: relation.anchor_text ?? null,
      position: relation.position,
    })),
  };
}

function signature(state: EditorState): string {
  return JSON.stringify(state);
}

function parseKeywords(value: string): string[] {
  return [...new Set(value.split(/[،,]/).map((item) => item.trim()).filter(Boolean))];
}

function errorMessage(error: unknown): string {
  return isApiError(error)
    ? error.message
    : "ذخیره محتوا انجام نشد. ارتباط سرویس و داده‌های فرم را بررسی کنید.";
}

function LabeledField({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <label className="grid gap-2 text-xs font-bold text-[color:var(--steam)]">
      {label}
      {children}
    </label>
  );
}

function previewAuthor(
  authors: AdminContentAuthor[],
  authorId: string,
): AdminContentAuthor | null {
  return authors.find((author) => author.id === authorId) ?? null;
}

export function EditorialContentDialog({
  entryId,
  authors,
  onClose,
  onSaved,
}: {
  entryId: string | null;
  authors: AdminContentAuthor[];
  onClose: () => void;
  onSaved: (entry: AdminContentDetail) => void;
}) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const queryClient = useQueryClient();
  const detailQuery = useQuery(adminContentDetailQueryOptions(entryId ?? ""));
  const [state, setState] = useState<EditorState | null>(null);
  const [baseline, setBaseline] = useState("");
  const [tab, setTab] = useState<"edit" | "preview">("edit");
  const [section, setSection] = useState<"identity" | "blocks" | "relations" | "seo">(
    "identity",
  );
  const [validationErrors, setValidationErrors] = useState<string[]>([]);

  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    if (entryId && !dialog.open) dialog.showModal();
    if (!entryId && dialog.open) dialog.close();
  }, [entryId]);

  useEffect(() => {
    if (!detailQuery.data) return;
    const next = stateFromEntry(detailQuery.data);
    setState(next);
    setBaseline(signature(next));
    setValidationErrors([]);
  }, [detailQuery.data]);

  const dirty = state ? signature(state) !== baseline : false;
  const selectedAuthor = useMemo(
    () => (state ? previewAuthor(authors, state.authorId) : null),
    [authors, state],
  );

  const requestClose = () => {
    if (dirty && !window.confirm("تغییرات ذخیره نشده‌اند. ویرایش بسته شود؟")) {
      return;
    }
    onClose();
  };

  const mutation = useMutation({
    mutationFn: ({ id, input }: { id: string; input: UpdateContentEntryInput }) =>
      updateContentEntry(id, input),
    onSuccess: async (entry) => {
      const next = stateFromEntry(entry);
      setState(next);
      setBaseline(signature(next));
      setValidationErrors([]);
      queryClient.setQueryData(["admin", "content", entry.id], entry);
      await queryClient.invalidateQueries({ queryKey: ["admin", "content"] });
      onSaved(entry);
    },
  });

  const validate = (current: EditorState): string[] => {
    const errors: string[] = [];
    if (!current.authorId) errors.push("نویسنده فعال انتخاب نشده است.");
    if (!current.title.trim()) errors.push("عنوان محتوا خالی است.");
    if (!current.slug.trim()) errors.push("Slug محتوا خالی است.");
    if (!current.canonicalPath.startsWith("/")) {
      errors.push("Canonical باید با / شروع شود.");
    }
    const blocks = contentBlockSchema.array().min(1).max(200).safeParse(current.blocks);
    if (!blocks.success) {
      errors.push(
        ...blocks.error.issues.slice(0, 8).map((issue) =>
          `بلوک ${Number(issue.path[0] ?? 0) + 1}: ${issue.message}`,
        ),
      );
    }
    current.relations.forEach((relation, index) => {
      if (!relation.target_key.trim()) {
        errors.push(`کلید مقصد رابطه ${index + 1} خالی است.`);
      }
    });
    return errors;
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (!entryId || !state) return;
    const errors = validate(state);
    setValidationErrors(errors);
    if (errors.length) return;

    mutation.mutate({
      id: entryId,
      input: {
        expected_content_hash: state.expectedHash,
        author_id: state.authorId || null,
        type: state.type,
        title: state.title.trim(),
        slug: state.slug.trim(),
        canonical_path: state.canonicalPath.trim(),
        excerpt: state.excerpt.trim(),
        body: state.blocks,
        seo_title: state.seoTitle.trim(),
        seo_description: state.seoDescription.trim(),
        og_title: state.ogTitle.trim() || null,
        og_description: state.ogDescription.trim() || null,
        og_media_url: state.ogMediaUrl.trim() || null,
        robots_index: state.robotsIndex,
        robots_follow: state.robotsFollow,
        schema_type: state.schemaType,
        keywords: parseKeywords(state.keywords),
        relations: state.relations.map((relation, position) => ({
          ...relation,
          target_key: relation.target_key.trim(),
          anchor_text: relation.anchor_text?.trim() || null,
          position,
        })),
      },
    });
  };

  const reloadLatest = async () => {
    const result = await detailQuery.refetch();
    if (!result.data) return;
    const next = stateFromEntry(result.data);
    setState(next);
    setBaseline(signature(next));
    setValidationErrors([]);
    mutation.reset();
  };

  return (
    <dialog
      ref={dialogRef}
      onCancel={(event) => {
        event.preventDefault();
        requestClose();
      }}
      onClose={() => {
        if (entryId) onClose();
      }}
      className="m-auto h-[94dvh] w-[min(96rem,calc(100%-1rem))] max-w-none overflow-hidden rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-0 text-[color:var(--steam)] shadow-2xl backdrop:bg-black/80"
    >
      <header className="flex flex-wrap items-center justify-between gap-4 border-b border-[color:var(--mid)] px-5 py-4">
        <div>
          <p className="text-xs font-bold text-[color:var(--roast)]">EDITORIAL WORKSPACE</p>
          <h2 className="mt-1 text-xl font-bold">
            {detailQuery.data?.title ?? "ویرایش محتوا"}
          </h2>
        </div>
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setTab("edit")}
            className={`rounded-xl px-4 py-2 text-xs font-bold ${tab === "edit" ? "bg-[color:var(--roast)] text-[color:var(--night)]" : "border border-[color:var(--mid)]"}`}
          >
            ویرایش
          </button>
          <button
            type="button"
            onClick={() => setTab("preview")}
            className={`rounded-xl px-4 py-2 text-xs font-bold ${tab === "preview" ? "bg-[color:var(--roast)] text-[color:var(--night)]" : "border border-[color:var(--mid)]"}`}
          >
            پیش‌نمایش
          </button>
          <button
            type="button"
            onClick={requestClose}
            className="rounded-xl border border-[color:var(--mid)] px-4 py-2 text-xs font-bold"
          >
            بستن
          </button>
        </div>
      </header>

      <div className="h-[calc(94dvh-5rem)] overflow-y-auto">
        {detailQuery.isPending || !state ? (
          <div className="grid min-h-[60vh] place-items-center" role="status">
            <p className="text-sm text-[color:var(--light)]">در حال دریافت نسخه ویرایش…</p>
          </div>
        ) : detailQuery.isError ? (
          <div className="mx-auto max-w-xl p-8">
            <Alert variant="danger" title="محتوا دریافت نشد">
              {errorMessage(detailQuery.error)}
            </Alert>
            <Button className="mt-4" onClick={() => detailQuery.refetch()}>
              تلاش مجدد
            </Button>
          </div>
        ) : tab === "preview" ? (
          <article className="mx-auto max-w-4xl px-5 py-8">
            <header className="border-b border-[color:var(--mid)] pb-8">
              <p className="text-xs font-bold uppercase tracking-[0.18em] text-[color:var(--roast)]">
                {state.type}
              </p>
              <h1 className="mt-3 text-3xl font-bold leading-tight sm:text-5xl">
                {state.title || "عنوان محتوا"}
              </h1>
              {state.excerpt ? (
                <p className="mt-5 text-base leading-9 text-[color:var(--light)]">
                  {state.excerpt}
                </p>
              ) : null}
              <p className="mt-4 text-xs text-[color:var(--light)]">
                نویسنده: {selectedAuthor?.name ?? "تعیین نشده"}
              </p>
            </header>
            <div className="mt-8">
              <StructuredContentBlocks blocks={state.blocks} contentHash="admin-preview" />
            </div>
          </article>
        ) : (
          <form onSubmit={submit} className="grid min-h-full lg:grid-cols-[15rem_1fr]">
            <aside className="border-b border-[color:var(--mid)] p-4 lg:border-b-0 lg:border-e">
              <nav className="grid gap-2">
                {(
                  [
                    ["identity", "مشخصات صفحه"],
                    ["blocks", `بلوک‌ها (${state.blocks.length})`],
                    ["relations", `روابط (${state.relations.length})`],
                    ["seo", "سئو و ایندکس"],
                  ] as const
                ).map(([value, label]) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => setSection(value)}
                    className={`rounded-xl px-4 py-3 text-start text-sm font-bold ${section === value ? "bg-[color:var(--roast)] text-[color:var(--night)]" : "hover:bg-white/5"}`}
                  >
                    {label}
                  </button>
                ))}
              </nav>
              <div className="mt-5 rounded-xl border border-[color:var(--mid)] p-3 text-[11px] leading-6 text-[color:var(--light)]">
                Hash بازشده:
                <code className="mt-1 block break-all" dir="ltr">
                  {state.expectedHash}
                </code>
              </div>
            </aside>

            <main className="p-5 sm:p-7">
              {validationErrors.length ? (
                <Alert variant="danger" title="فرم قابل ذخیره نیست">
                  <ul className="list-disc pe-5">
                    {validationErrors.map((error) => (
                      <li key={error}>{error}</li>
                    ))}
                  </ul>
                </Alert>
              ) : null}

              {mutation.isError ? (
                <div className="mt-4">
                  <Alert variant="danger" title="ذخیره انجام نشد">
                    {errorMessage(mutation.error)}
                  </Alert>
                  {isApiError(mutation.error) &&
                  mutation.error.code === "content.edit_conflict" ? (
                    <Button variant="outline" className="mt-3" onClick={reloadLatest}>
                      دریافت نسخه جدید
                    </Button>
                  ) : null}
                </div>
              ) : null}

              {section === "identity" ? (
                <section className="mt-5 grid gap-4 md:grid-cols-2">
                  <LabeledField label="نوع محتوا">
                    <select
                      value={state.type}
                      onChange={(event) =>
                        setState({ ...state, type: event.target.value as AdminContentType })
                      }
                      className={inputClass}
                    >
                      {(Object.keys(typeLabels) as AdminContentType[]).map((type) => (
                        <option key={type} value={type}>
                          {typeLabels[type]}
                        </option>
                      ))}
                    </select>
                  </LabeledField>
                  <LabeledField label="نویسنده">
                    <select
                      value={state.authorId}
                      onChange={(event) =>
                        setState({ ...state, authorId: event.target.value })
                      }
                      className={inputClass}
                    >
                      <option value="">انتخاب نویسنده</option>
                      {authors
                        .filter((author) => author.is_active)
                        .map((author) => (
                          <option key={author.id} value={author.id}>
                            {author.name}
                          </option>
                        ))}
                    </select>
                  </LabeledField>
                  <LabeledField label="عنوان صفحه">
                    <input
                      value={state.title}
                      onChange={(event) => setState({ ...state, title: event.target.value })}
                      className={inputClass}
                    />
                  </LabeledField>
                  <LabeledField label="Slug">
                    <input
                      dir="ltr"
                      value={state.slug}
                      onChange={(event) => setState({ ...state, slug: event.target.value })}
                      className={`${inputClass} text-left`}
                    />
                  </LabeledField>
                  <LabeledField label="Canonical path">
                    <input
                      dir="ltr"
                      value={state.canonicalPath}
                      onChange={(event) =>
                        setState({ ...state, canonicalPath: event.target.value })
                      }
                      className={`${inputClass} text-left`}
                    />
                  </LabeledField>
                  <div className="md:col-span-2">
                    <LabeledField label="خلاصه صفحه">
                      <textarea
                        rows={4}
                        value={state.excerpt}
                        onChange={(event) =>
                          setState({ ...state, excerpt: event.target.value })
                        }
                        className={inputClass}
                      />
                    </LabeledField>
                  </div>
                </section>
              ) : null}

              {section === "blocks" ? (
                <div className="mt-5">
                  <StructuredBlockEditor
                    blocks={state.blocks}
                    onChange={(blocks) => setState({ ...state, blocks })}
                  />
                </div>
              ) : null}

              {section === "relations" ? (
                <div className="mt-5">
                  <ContentRelationEditor
                    relations={state.relations}
                    onChange={(relations) => setState({ ...state, relations })}
                  />
                </div>
              ) : null}

              {section === "seo" ? (
                <section className="mt-5 grid gap-4 md:grid-cols-2">
                  <LabeledField label="عنوان SEO">
                    <input
                      value={state.seoTitle}
                      onChange={(event) =>
                        setState({ ...state, seoTitle: event.target.value })
                      }
                      className={inputClass}
                    />
                  </LabeledField>
                  <LabeledField label="نوع Schema">
                    <select
                      value={state.schemaType}
                      onChange={(event) =>
                        setState({
                          ...state,
                          schemaType: event.target.value as AdminSchemaType,
                        })
                      }
                      className={inputClass}
                    >
                      {(Object.keys(schemaLabels) as AdminSchemaType[]).map((schema) => (
                        <option key={schema} value={schema}>
                          {schemaLabels[schema]}
                        </option>
                      ))}
                    </select>
                  </LabeledField>
                  <div className="md:col-span-2">
                    <LabeledField label="توضیح SEO">
                      <textarea
                        rows={4}
                        value={state.seoDescription}
                        onChange={(event) =>
                          setState({ ...state, seoDescription: event.target.value })
                        }
                        className={inputClass}
                      />
                    </LabeledField>
                  </div>
                  <LabeledField label="عنوان Open Graph">
                    <input
                      value={state.ogTitle}
                      onChange={(event) => setState({ ...state, ogTitle: event.target.value })}
                      className={inputClass}
                    />
                  </LabeledField>
                  <LabeledField label="تصویر Open Graph HTTPS">
                    <input
                      dir="ltr"
                      value={state.ogMediaUrl}
                      onChange={(event) =>
                        setState({ ...state, ogMediaUrl: event.target.value })
                      }
                      className={`${inputClass} text-left`}
                    />
                  </LabeledField>
                  <div className="md:col-span-2">
                    <LabeledField label="توضیح Open Graph">
                      <textarea
                        rows={3}
                        value={state.ogDescription}
                        onChange={(event) =>
                          setState({ ...state, ogDescription: event.target.value })
                        }
                        className={inputClass}
                      />
                    </LabeledField>
                  </div>
                  <div className="md:col-span-2">
                    <LabeledField label="کلمات کلیدی با ویرگول جدا شوند">
                      <input
                        value={state.keywords}
                        onChange={(event) =>
                          setState({ ...state, keywords: event.target.value })
                        }
                        className={inputClass}
                      />
                    </LabeledField>
                  </div>
                  <label className="flex items-start gap-3 rounded-xl border border-[color:var(--mid)] p-3 text-sm">
                    <input
                      type="checkbox"
                      checked={state.robotsIndex}
                      onChange={(event) =>
                        setState({ ...state, robotsIndex: event.target.checked })
                      }
                    />
                    پس از انتشار وارد Sitemap شود
                  </label>
                  <label className="flex items-start gap-3 rounded-xl border border-[color:var(--mid)] p-3 text-sm">
                    <input
                      type="checkbox"
                      checked={state.robotsFollow}
                      onChange={(event) =>
                        setState({ ...state, robotsFollow: event.target.checked })
                      }
                    />
                    لینک‌های صفحه Follow باشند
                  </label>
                </section>
              ) : null}

              <footer className="sticky bottom-0 mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-[color:var(--mid)] bg-[color:var(--dark)] py-4">
                <p className="text-xs text-[color:var(--light)]">
                  {dirty ? "تغییرات ذخیره‌نشده وجود دارد." : "نسخه ویرایش با سرور همگام است."}
                </p>
                <Button type="submit" loading={mutation.isPending} disabled={!dirty}>
                  ذخیره تغییرات
                </Button>
              </footer>
            </main>
          </form>
        )}
      </div>
    </dialog>
  );
}
