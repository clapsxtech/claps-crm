/** Thin client for the Worker's /api/* routes, which forward to the ERP's sales API. */

export type Id = number;

export interface Unit {
  id: Id;
  code: string;
  name: string;
  timezone: string;
  role: string | null;
  actions: string[];
}

export interface User {
  id: Id;
  name: string;
  email: string;
  units: Unit[];
}

export interface Page<T> {
  data: T[];
  page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export interface Lead {
  id: Id;
  name: string;
  phone: string;
  email: string | null;
  enquiry_date: string;
  status: "LEAD" | "JOINED" | "LOST";
  grade: string | null;
  subject: string | null;
  syllabus: string | null;
  source: string | null;
  country: string | null;
  notes: string | null;
  student_phone: string | null;
  assigned_to: Id | null;
  assigned_name: string | null;
  is_hot: number | boolean;
  interest_status: string | null;
  next_follow_up_on: string | null;
  joined_on: string | null;
  admission_code: string | null;
  lost_reason: string | null;
  learning_requirement: string | null;
  concern: string | null;
  about_student: string | null;
  about_parent: string | null;
  canonical_student_name: string | null;
  canonical_student_code: string | null;
  monthly_fee_paise: number | null;
  crm_stage: Stage;
  created_at: string;
}

export type Stage = "new" | "contacted" | "demo_scheduled" | "demo_attended" | "joined" | "lost";

export interface Activity {
  id: Id;
  kind: string;
  channel: string | null;
  outcome: string | null;
  summary: string;
  occurred_at: string;
  actor_name: string | null;
}

export interface Demo {
  id: Id;
  lead_id: Id;
  scheduled_on: string;
  scheduled_time: string;
  status: "SCHEDULED" | "RESCHEDULED" | "ATTENDED" | "CONVERTED" | "JOINED" | "CANCELLED" | "NO_SHOW";
  meet_link: string | null;
  scheduling_notes: string | null;
  teacher_name: string | null;
  teacher_phone: string | null;
  subject_name: string | null;
  lead_name?: string;
  phone?: string;
  lead_grade?: string | null;
  lead_syllabus?: string | null;
  country?: string | null;
  timezone?: string | null;
  duration_minutes?: number | null;
  matching_grade?: string | null;
  matching_syllabus?: string | null;
  matching_medium?: string | null;
  starts_at: string | null;
  ends_at: string | null;
  feedback_outcome: string | null;
}

export interface Reminder {
  id: Id;
  lead_id: Id;
  due_on: string;
  due_time: string | null;
  text: string;
  is_completed: number | boolean;
  lead_name?: string;
  phone?: string;
}

export interface Transfer {
  id: Id;
  lead_id: Id;
  status: string;
  from_user_id: Id;
  to_user_id: Id;
  lead_name: string;
  sender_name: string | null;
  recipient_name: string | null;
  created_at: string;
}

export interface LeadDetail {
  lead: Lead;
  contact: { dial: string; whatsapp: string | null };
  activities: Activity[];
  demos: Demo[];
  reminders: Reminder[];
  transfers: Transfer[];
}

export interface Overview {
  business: { id: Id; code: string; name: string; timezone: string };
  today: string;
  stage_labels: Record<Stage, string>;
  stages: Record<Stage, number>;
  followups: { today: number; overdue: number; unscheduled: number };
  active_leads: number;
  joined_this_month: number;
  upcoming_demos: number;
  due_reminders: number;
  pending_transfers: number;
  can_manage: boolean;
}

export interface Teacher {
  id: Id;
  code: string;
  name: string;
  phone: string | null;
  qualification: string | null;
  subjects: string | null;
  classes: string | null;
  mediums: string | null;
  levels: string | null;
  level_list: string[];
  category: string | null;
  tt_hours: string;
  registered_at: string | null;
  created_at: string | null;
}

export interface TeacherPage extends Partial<Page<Teacher>> {
  data: Teacher[];
  subjects: { id: Id; name: string }[];
  levels: Record<string, string>;
  categories: string[];
}

export class ApiError extends Error {
  constructor(public status: number, message: string, public fields: Record<string, string[]> = {}, public body: unknown = null) {
    super(message);
  }
}

let onSignedOut: () => void = () => {};
export function setSignedOutHandler(handler: () => void) {
  onSignedOut = handler;
}

export async function api<T>(path: string, init: { method?: string; body?: unknown; signal?: AbortSignal } = {}): Promise<T> {
  const method = init.method ?? (init.body === undefined ? "GET" : "POST");
  const headers: Record<string, string> = { Accept: "application/json" };
  if (method !== "GET") headers["x-claps-sales"] = "1";
  if (init.body !== undefined) headers["Content-Type"] = "application/json";
  let response: Response;
  try {
    response = await fetch(`/api${path}`, {
      method, headers, credentials: "same-origin", signal: init.signal,
      body: init.body === undefined ? undefined : JSON.stringify(init.body),
    });
  } catch (error) {
    if ((error as Error).name === "AbortError") throw error;
    throw new ApiError(0, "You appear to be offline. Check your connection and try again.");
  }
  const text = await response.text();
  let body: any = null;
  try { body = text ? JSON.parse(text) : null; } catch { body = null; }
  if (response.status === 401 && path !== "/login") onSignedOut();
  if (!response.ok && response.status !== 202) {
    const fields: Record<string, string[]> = body?.errors ?? {};
    const first = Object.values(fields)[0]?.[0];
    const fallback = response.status === 403 ? "Your role does not allow this." : response.status === 404 ? "Not found." : response.status >= 500 ? "Something went wrong on the server. Try again." : "Request failed.";
    throw new ApiError(response.status, first ?? body?.message ?? fallback, fields, body);
  }
  return body as T;
}

export const unitPath = (unit: Id, path: string) => `/units/${unit}${path}`;

export function query(params: Record<string, string | number | boolean | null | undefined>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "" || value === false) continue;
    search.set(key, value === true ? "1" : String(value));
  }
  const text = search.toString();
  return text ? `?${text}` : "";
}
