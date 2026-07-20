import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type ReactNode,
  type TextareaHTMLAttributes,
} from "react";
import { X } from "lucide-react";

function classes(...values: Array<string | false | null | undefined>) {
  return values.filter(Boolean).join(" ");
}

export type ButtonVariant = "primary" | "secondary" | "outline" | "ghost" | "danger";
export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  loading?: boolean;
  loadingLabel?: string;
}

const buttonVariants: Record<ButtonVariant, string> = {
  primary: "border-transparent bg-[color:var(--roast)] text-[color:var(--night)] hover:brightness-110",
  secondary: "border-[color:var(--mid)] bg-[color:var(--dark)] text-[color:var(--steam)] hover:border-[color:var(--roast)]",
  outline: "border-[color:var(--roast)] bg-transparent text-[color:var(--roast)] hover:bg-[color:var(--roast)] hover:text-[color:var(--night)]",
  ghost: "border-transparent bg-transparent text-[color:var(--light)] hover:bg-white/5 hover:text-[color:var(--steam)]",
  danger: "border-red-500 bg-red-600 text-white hover:bg-red-500",
};

export function Button({
  variant = "primary",
  loading = false,
  loadingLabel = "در حال پردازش",
  className,
  children,
  disabled,
  type = "button",
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={classes(
        "inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-5 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--roast)] disabled:cursor-not-allowed disabled:opacity-50",
        buttonVariants[variant],
        className,
      )}
      {...props}
    >
      {loading ? <><span aria-hidden className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />{loadingLabel}</> : children}
    </button>
  );
}

export function Container({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={classes("mx-auto w-full max-w-6xl px-4 sm:px-6", className)}>{children}</div>;
}

export function PageHeader({
  eyebrow,
  title,
  description,
  actions,
}: {
  eyebrow?: string;
  title: ReactNode;
  description?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <header className="grid gap-5 md:grid-cols-[1fr_auto] md:items-end">
      <div>
        {eyebrow ? <p className="text-xs font-bold tracking-[0.2em] text-[color:var(--roast)]">{eyebrow}</p> : null}
        <h1 className="mt-2 text-3xl font-bold text-[color:var(--steam)] sm:text-4xl">{title}</h1>
        {description ? <div className="mt-3 max-w-2xl text-sm leading-7 text-[color:var(--light)]">{description}</div> : null}
      </div>
      {actions ? <div className="flex flex-wrap gap-3">{actions}</div> : null}
    </header>
  );
}

interface FieldBase {
  label: string;
  error?: string;
  description?: string;
}

export function TextField({
  label,
  error,
  description,
  id: providedId,
  className,
  ...props
}: FieldBase & InputHTMLAttributes<HTMLInputElement>) {
  const generated = useId();
  const id = providedId || generated;
  return (
    <label htmlFor={id} className="grid gap-2 text-sm font-bold text-[color:var(--steam)]">
      {label}
      <input
        id={id}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? `${id}-error` : description ? `${id}-description` : undefined}
        className={classes(
          "min-h-12 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] px-4 text-sm font-normal text-[color:var(--steam)] outline-none placeholder:text-[color:var(--light)]/60 focus:border-[color:var(--roast)] focus:ring-2 focus:ring-[color:var(--roast)]/20",
          error && "border-red-500",
          className,
        )}
        {...props}
      />
      {error ? <span id={`${id}-error`} role="alert" className="text-xs font-normal text-red-300">{error}</span> : null}
      {!error && description ? <span id={`${id}-description`} className="text-xs font-normal leading-6 text-[color:var(--light)]">{description}</span> : null}
    </label>
  );
}

export function TextareaField({
  label,
  error,
  description,
  id: providedId,
  className,
  ...props
}: FieldBase & TextareaHTMLAttributes<HTMLTextAreaElement>) {
  const generated = useId();
  const id = providedId || generated;
  return (
    <label htmlFor={id} className="grid gap-2 text-sm font-bold text-[color:var(--steam)]">
      {label}
      <textarea
        id={id}
        aria-invalid={Boolean(error)}
        className={classes("min-h-32 resize-y rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-4 text-sm font-normal outline-none focus:border-[color:var(--roast)]", error && "border-red-500", className)}
        {...props}
      />
      {error ? <span role="alert" className="text-xs font-normal text-red-300">{error}</span> : null}
      {!error && description ? <span className="text-xs font-normal text-[color:var(--light)]">{description}</span> : null}
    </label>
  );
}

export function CheckboxField({ label, description, ...props }: { label: string; description?: string } & InputHTMLAttributes<HTMLInputElement>) {
  return (
    <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-[color:var(--mid)] bg-[color:var(--night)] p-3 text-sm">
      <input type="checkbox" className="mt-1 size-4 accent-[color:var(--roast)]" {...props} />
      <span><span className="block font-bold">{label}</span>{description ? <span className="mt-1 block text-xs leading-6 text-[color:var(--light)]">{description}</span> : null}</span>
    </label>
  );
}

export function Alert({
  title,
  children,
  variant = "info",
}: {
  title?: ReactNode;
  children: ReactNode;
  variant?: "info" | "success" | "warning" | "danger";
}) {
  const styles = {
    info: "border-blue-400/40 bg-blue-950/20",
    success: "border-emerald-400/40 bg-emerald-950/20",
    warning: "border-amber-400/40 bg-amber-950/20",
    danger: "border-red-400/40 bg-red-950/20",
  };
  return <div role={variant === "danger" ? "alert" : "status"} className={classes("rounded-2xl border p-4 text-sm leading-7 text-[color:var(--light)]", styles[variant])}>{title ? <p className="mb-1 font-bold text-[color:var(--steam)]">{title}</p> : null}{children}</div>;
}

export function EmptyState({ title, description, action }: { title: ReactNode; description?: ReactNode; action?: ReactNode }) {
  return <div className="rounded-2xl border border-dashed border-[color:var(--mid)] p-10 text-center"><h2 className="font-bold">{title}</h2>{description ? <p className="mt-2 text-sm leading-7 text-[color:var(--light)]">{description}</p> : null}{action ? <div className="mt-5">{action}</div> : null}</div>;
}

export function Skeleton({ className }: { className?: string }) {
  return <div aria-hidden className={classes("animate-pulse rounded-xl bg-[color:var(--dark)]", className)} />;
}

export interface FormSummaryError { fieldId: string; message: string }
export function FormSummary({ errors }: { errors: readonly FormSummaryError[] }) {
  if (!errors.length) return null;
  return <div role="alert" className="rounded-2xl border border-red-400/40 bg-red-950/20 p-4"><p className="font-bold">خطاهای فرم را بررسی کنید</p><ul className="mt-2 list-disc pe-5 text-sm text-red-200">{errors.map((error) => <li key={`${error.fieldId}-${error.message}`}><a href={`#${error.fieldId}`} className="underline">{error.message}</a></li>)}</ul></div>;
}

function Overlay({
  open,
  onOpenChange,
  title,
  description,
  children,
  variant,
}: {
  open: boolean;
  onOpenChange: (value: boolean) => void;
  title: ReactNode;
  description?: ReactNode;
  children: ReactNode;
  variant: "dialog" | "drawer";
}) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) dialog.showModal();
    if (!open && dialog.open) dialog.close();
  }, [open]);
  return (
    <dialog
      ref={ref}
      onCancel={(event) => { event.preventDefault(); onOpenChange(false); }}
      onClose={() => onOpenChange(false)}
      onClick={(event) => { if (event.target === event.currentTarget) onOpenChange(false); }}
      className={classes(
        "m-auto max-h-[90dvh] w-[calc(100%-2rem)] overflow-hidden border border-[color:var(--mid)] bg-[color:var(--dark)] p-0 text-[color:var(--steam)] shadow-2xl backdrop:bg-black/75",
        variant === "dialog" ? "max-w-xl rounded-2xl" : "me-0 h-dvh max-h-dvh max-w-md rounded-none",
      )}
    >
      <header className="flex items-start justify-between gap-4 border-b border-[color:var(--mid)] p-5"><div><h2 className="text-xl font-bold">{title}</h2>{description ? <p className="mt-2 text-sm text-[color:var(--light)]">{description}</p> : null}</div><button type="button" aria-label="بستن" onClick={() => onOpenChange(false)} className="rounded-lg p-2 hover:bg-white/5"><X size={18} /></button></header>
      <div className="max-h-[calc(90dvh-5rem)] overflow-y-auto p-5">{children}</div>
    </dialog>
  );
}

export function Dialog(props: Omit<Parameters<typeof Overlay>[0], "variant">) { return <Overlay {...props} variant="dialog" />; }
export function Drawer(props: Omit<Parameters<typeof Overlay>[0], "variant">) { return <Overlay {...props} variant="drawer" />; }

interface ToastInput { title: string; description?: string; variant?: "info" | "success" | "warning" | "danger" }
interface ToastRecord extends ToastInput { id: number }
const ToastContext = createContext<{ pushToast: (toast: ToastInput) => void } | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastRecord[]>([]);
  const counter = useRef(0);
  const pushToast = useCallback((toast: ToastInput) => {
    const id = ++counter.current;
    setToasts((current) => [...current, { ...toast, id }].slice(-4));
    window.setTimeout(() => setToasts((current) => current.filter((item) => item.id !== id)), 5000);
  }, []);
  const value = useMemo(() => ({ pushToast }), [pushToast]);
  return <ToastContext.Provider value={value}>{children}<div aria-live="polite" className="fixed bottom-20 start-4 z-[100] grid w-[min(24rem,calc(100%-2rem))] gap-3 md:bottom-4">{toasts.map((toast) => <div key={toast.id} className="rounded-2xl border border-[color:var(--mid)] bg-[color:var(--dark)] p-4 shadow-2xl"><p className="font-bold">{toast.title}</p>{toast.description ? <p className="mt-1 text-xs leading-6 text-[color:var(--light)]">{toast.description}</p> : null}</div>)}</div></ToastContext.Provider>;
}

export function useToast() {
  const context = useContext(ToastContext);
  if (!context) throw new Error("useToast must be used inside ToastProvider");
  return context;
}
