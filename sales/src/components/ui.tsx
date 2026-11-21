import { createContext, useCallback, useContext, useEffect, useId, useRef, useState, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from "react";
import { AlertCircle, CheckCircle2, Loader2, X, type LucideIcon } from "lucide-react";

export function cx(...parts: (string | false | null | undefined)[]) {
  return parts.filter(Boolean).join(" ");
}

type Variant = "primary" | "dark" | "secondary" | "ghost" | "danger";
const VARIANTS: Record<Variant, string> = {
  primary: "bg-brand-primary text-white hover:bg-brand-primary-hover shadow-sm shadow-blue-600/20",
  dark: "bg-brand-ink text-white hover:bg-slate-800",
  secondary: "bg-white text-brand-ink border border-brand-border hover:bg-brand-fill",
  ghost: "text-brand-soft hover:bg-slate-100",
  danger: "bg-white text-brand-danger border border-rose-200 hover:bg-rose-50",
};

export function Button({ variant = "secondary", icon: Icon, busy, className, children, size = "md", ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant; icon?: LucideIcon; busy?: boolean; size?: "sm" | "md" | "lg" }) {
  const sizes = { sm: "h-8 px-3 text-xs gap-1.5", md: "h-10 px-4 text-sm gap-2", lg: "h-12 px-5 text-sm gap-2" };
  return (
    <button {...props} disabled={props.disabled || busy}
      className={cx("inline-flex items-center justify-center rounded-xl font-semibold transition disabled:cursor-not-allowed disabled:opacity-55", sizes[size], VARIANTS[variant], className)}>
      {busy ? <Loader2 className="size-4 animate-spin" aria-hidden /> : Icon ? <Icon className="size-4" aria-hidden /> : null}
      {children}
    </button>
  );
}

export function IconButton({ icon: Icon, label, className, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { icon: LucideIcon; label: string }) {
  return (
    <button type="button" title={label} aria-label={label} {...props}
      className={cx("inline-flex size-10 items-center justify-center rounded-xl border border-brand-border bg-white text-brand-soft transition hover:bg-brand-fill hover:text-brand-ink disabled:opacity-50", className)}>
      <Icon className="size-4" aria-hidden />
    </button>
  );
}

export function Card({ className, children }: { className?: string; children: ReactNode }) {
  return <section className={cx("card", className)}>{children}</section>;
}

export function Field({ label, icon: Icon, error, hint, required, children }: { label: string; icon?: LucideIcon; error?: string; hint?: string; required?: boolean; children: (id: string) => ReactNode }) {
  const id = useId();
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="field-label">{label}{required && <span className="ml-0.5 text-brand-danger">*</span>}</label>
      <div className="relative">
        {Icon && <Icon className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden />}
        {children(id)}
      </div>
      {error ? <p className="text-xs font-medium text-brand-danger">{error}</p> : hint ? <p className="text-xs text-brand-muted">{hint}</p> : null}
    </div>
  );
}

export const Input = ({ className, withIcon, ...props }: InputHTMLAttributes<HTMLInputElement> & { withIcon?: boolean }) =>
  <input {...props} className={cx("control", withIcon && "pl-9", className)} />;
export const Select = ({ className, withIcon, children, ...props }: SelectHTMLAttributes<HTMLSelectElement> & { withIcon?: boolean }) =>
  <select {...props} className={cx("control appearance-none bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%2394a3b8%22><path d=%22M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z%22/></svg>')] bg-[length:1.1rem] bg-[right_0.6rem_center] bg-no-repeat pr-9", withIcon && "pl-9", className)}>{children}</select>;
export const Textarea = ({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) =>
  <textarea {...props} className={cx("control min-h-24 resize-y", className)} />;

export function Pill({ className, children }: { className: string; children: ReactNode }) {
  return <span className={cx("inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset", className)}>{children}</span>;
}

export function Spinner({ label = "Loading" }: { label?: string }) {
  return <div role="status" className="flex items-center justify-center gap-2 py-12 text-sm text-brand-muted"><Loader2 className="size-5 animate-spin" aria-hidden />{label}…</div>;
}

export function EmptyState({ icon: Icon, title, children, action }: { icon: LucideIcon; title: string; children?: ReactNode; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center px-6 py-12 text-center">
      <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-blue-50 text-brand-primary"><Icon className="size-5" aria-hidden /></div>
      <p className="font-semibold">{title}</p>
      {children && <p className="mt-1 max-w-sm text-sm text-brand-muted">{children}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

export function ErrorNote({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div role="alert" className="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
      <AlertCircle className="mt-0.5 size-4 shrink-0" aria-hidden />
      <div className="flex-1">{message}</div>
      {onRetry && <button type="button" onClick={onRetry} className="font-semibold underline">Try again</button>}
    </div>
  );
}

export function Segmented<T extends string>({ value, options, onChange, label }: { value: T; options: { value: T; label: ReactNode }[]; onChange: (value: T) => void; label: string }) {
  return (
    <div role="tablist" aria-label={label} className="inline-flex flex-wrap gap-1 rounded-2xl border border-brand-border bg-white p-1">
      {options.map((option) => (
        <button key={option.value} type="button" role="tab" aria-selected={value === option.value} onClick={() => onChange(option.value)}
          className={cx("rounded-xl px-3.5 py-1.5 text-sm font-medium transition", value === option.value ? "bg-brand-ink text-white" : "text-brand-soft hover:bg-slate-100")}>
          {option.label}
        </button>
      ))}
    </div>
  );
}

export function Modal({ open, title, subtitle, icon: Icon, onClose, children, footer }: { open: boolean; title: string; subtitle?: string; icon?: LucideIcon; onClose: () => void; children: ReactNode; footer?: ReactNode }) {
  const panel = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!open) return;
    const previous = document.activeElement as HTMLElement | null;
    const onKey = (event: KeyboardEvent) => { if (event.key === "Escape") onClose(); };
    document.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    panel.current?.querySelector<HTMLElement>("input, select, textarea, button")?.focus();
    return () => { document.removeEventListener("keydown", onKey); document.body.style.overflow = ""; previous?.focus(); };
  }, [open, onClose]);
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 backdrop-blur-sm sm:items-center sm:p-4" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
      <div ref={panel} role="dialog" aria-modal="true" aria-label={title} className="flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl">
        <div className="flex items-center gap-3 border-b border-brand-border bg-brand-fill px-5 py-4">
          {Icon && <div className="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-brand-primary"><Icon className="size-5" aria-hidden /></div>}
          <div className="min-w-0 flex-1">
            <h2 className="truncate text-lg font-semibold">{title}</h2>
            {subtitle && <p className="truncate text-xs font-semibold uppercase tracking-wider text-brand-muted">{subtitle}</p>}
          </div>
          <button type="button" onClick={onClose} aria-label="Close" className="rounded-lg p-1.5 text-brand-muted hover:bg-slate-200"><X className="size-5" /></button>
        </div>
        <div className="flex-1 space-y-4 overflow-y-auto px-5 py-5">{children}</div>
        {footer && <div className="flex justify-end gap-2 border-t border-brand-border px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]">{footer}</div>}
      </div>
    </div>
  );
}

interface Toast { id: number; kind: "success" | "error"; text: string }
const ToastContext = createContext<(kind: Toast["kind"], text: string) => void>(() => {});

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const push = useCallback((kind: Toast["kind"], text: string) => {
    const id = Date.now() + Math.random();
    setToasts((list) => [...list, { id, kind, text }]);
    setTimeout(() => setToasts((list) => list.filter((toast) => toast.id !== id)), 4500);
  }, []);
  return (
    <ToastContext.Provider value={push}>
      {children}
      <div aria-live="polite" className="pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6">
        {toasts.map((toast) => (
          <div key={toast.id} className={cx("pointer-events-auto flex max-w-sm items-start gap-2 rounded-2xl border px-4 py-3 text-sm font-medium shadow-lg", toast.kind === "success" ? "border-emerald-200 bg-emerald-50 text-emerald-800" : "border-rose-200 bg-rose-50 text-rose-800")}>
            {toast.kind === "success" ? <CheckCircle2 className="mt-0.5 size-4 shrink-0" /> : <AlertCircle className="mt-0.5 size-4 shrink-0" />}
            {toast.text}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export const useToast = () => useContext(ToastContext);

export function Pager({ page, lastPage, total, onPage }: { page: number; lastPage: number; total: number; onPage: (page: number) => void }) {
  if (lastPage <= 1) return null;
  return (
    <div className="flex items-center justify-between px-1 pt-4 text-sm text-brand-muted">
      <span>Page {page} of {lastPage} · {total} total</span>
      <div className="flex gap-2">
        <Button size="sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>Previous</Button>
        <Button size="sm" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>Next</Button>
      </div>
    </div>
  );
}
