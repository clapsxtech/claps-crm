import type { Demo, Lead, Stage } from "./api";

export const STAGES: Record<Stage, { label: string; className: string }> = {
  new: { label: "New", className: "bg-slate-100 text-slate-700 ring-slate-200" },
  contacted: { label: "Contacted", className: "bg-sky-50 text-sky-700 ring-sky-200" },
  demo_scheduled: { label: "Demo scheduled", className: "bg-violet-50 text-violet-700 ring-violet-200" },
  demo_attended: { label: "Demo attended", className: "bg-amber-50 text-amber-800 ring-amber-200" },
  joined: { label: "Registered", className: "bg-emerald-50 text-emerald-700 ring-emerald-200" },
  lost: { label: "Lost", className: "bg-rose-50 text-rose-700 ring-rose-200" },
};

export const DEMO_STATUS: Record<Demo["status"], { label: string; className: string }> = {
  SCHEDULED: { label: "Scheduled", className: "bg-blue-50 text-blue-700 ring-blue-200" },
  RESCHEDULED: { label: "Rescheduled", className: "bg-violet-50 text-violet-700 ring-violet-200" },
  ATTENDED: { label: "Attended", className: "bg-emerald-50 text-emerald-700 ring-emerald-200" },
  CONVERTED: { label: "Converted", className: "bg-emerald-50 text-emerald-700 ring-emerald-200" },
  JOINED: { label: "Joined", className: "bg-emerald-50 text-emerald-700 ring-emerald-200" },
  CANCELLED: { label: "Cancelled", className: "bg-slate-100 text-slate-600 ring-slate-200" },
  NO_SHOW: { label: "No show", className: "bg-rose-50 text-rose-700 ring-rose-200" },
};

export const COUNTRIES = [
  { code: "IN", name: "India", dial: "+91" },
  { code: "AE", name: "United Arab Emirates", dial: "+971" },
  { code: "QA", name: "Qatar", dial: "+974" },
  { code: "SA", name: "Saudi Arabia", dial: "+966" },
  { code: "OM", name: "Oman", dial: "+968" },
  { code: "KW", name: "Kuwait", dial: "+965" },
  { code: "BH", name: "Bahrain", dial: "+973" },
  { code: "GB", name: "United Kingdom", dial: "+44" },
  { code: "US", name: "United States", dial: "+1" },
  { code: "CA", name: "Canada", dial: "+1" },
  { code: "AU", name: "Australia", dial: "+61" },
  { code: "SG", name: "Singapore", dial: "+65" },
];

export const leadName = (lead: Pick<Lead, "name"> & Partial<Pick<Lead, "canonical_student_name">>) =>
  (lead.canonical_student_name || lead.name || "").trim() || "Student name pending";

export const initials = (name: string) =>
  name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]!.toUpperCase()).join("") || "?";

export const truthy = (value: unknown) => value === true || value === 1 || value === "1";

function parseDate(value: string): Date | null {
  if (!value) return null;
  const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value.replace(" ", "T") + (value.length === 19 ? "Z" : ""));
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value: string | null | undefined, withYear = false): string {
  const date = value ? parseDate(value) : null;
  if (!date) return "—";
  return date.toLocaleDateString("en-IN", { day: "numeric", month: "short", ...(withYear ? { year: "numeric" } : {}) });
}

export function formatDateTime(value: string | null | undefined): string {
  const date = value ? parseDate(value) : null;
  if (!date) return "—";
  return date.toLocaleString("en-IN", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit" });
}

export function formatTime(value: string | null | undefined): string {
  if (!value) return "";
  const [h, m] = value.split(":").map(Number);
  if (Number.isNaN(h) || Number.isNaN(m)) return value;
  return `${((h + 11) % 12) + 1}:${String(m).padStart(2, "0")} ${h < 12 ? "AM" : "PM"}`;
}

export function relativeDay(value: string | null | undefined, today: string): string {
  if (!value) return "Not scheduled";
  if (value === today) return "Today";
  const diff = Math.round((new Date(`${value}T00:00:00`).getTime() - new Date(`${today}T00:00:00`).getTime()) / 86400000);
  if (diff === 1) return "Tomorrow";
  if (diff === -1) return "Yesterday";
  if (diff < 0) return `${-diff} days overdue`;
  return formatDate(value);
}

export function whatsappLink(phone: string | null | undefined, country?: string | null, text?: string): string | null {
  let digits = (phone ?? "").replace(/\D/g, "");
  const code = (country ?? "").toUpperCase();
  if (digits.length === 10 && ["IN", "IND", "INDIA", ""].includes(code)) digits = `91${digits}`;
  if (digits.length < 11 || digits.length > 15 || digits.startsWith("0")) return null;
  return `https://wa.me/${digits}${text ? `?text=${encodeURIComponent(text)}` : ""}`;
}

export function todayIn(timezone: string): string {
  try {
    return new Intl.DateTimeFormat("en-CA", { timeZone: timezone, year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
  } catch {
    return new Date().toISOString().slice(0, 10);
  }
}

export const roleLabel = (role: string | null) =>
  ({ superadmin: "Administrator", admin: "Team manager", staff: "Team manager", team_manager: "Team manager", sales_followups: "Sales", read_only: "Read-only" } as Record<string, string>)[role ?? ""] ?? "Member";
