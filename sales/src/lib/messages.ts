/**
 * WhatsApp message texts. These mirror App\Modules\Erp\Services\SalesPipeline in
 * clapsxtech/claps-erp (demoParentMessage, demoTeacherMessage, joinedMessage,
 * teacherProfileMessage) so the sales app and the ERP's Sales CRM send identical
 * messages. Keep the two in step when either changes.
 */
import type { Demo, Lead, Teacher } from "./api";

export interface Business { name: string; timezone: string }

const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

/** SalesPipeline::name: the canonical student name, then the given field, then a placeholder. */
export function pipelineName(record: Record<string, unknown>, field = "name"): string {
  const canonical = String(record.canonical_student_name ?? "").trim();
  const value = canonical || String(record[field] ?? "").trim();
  return value !== "" ? value : "Student name pending";
}

function lines(rows: [string, unknown][]): string {
  return rows.filter(([, value]) => value !== null && value !== undefined && value !== "" && value !== false && value !== 0 && value !== "0")
    .map(([label, value]) => `${label}: ${value}`).join("\n");
}

/** PHP "D, d M Y · h:i A". */
export function demoWhen(demo: Pick<Demo, "scheduled_on" | "scheduled_time">): string {
  const [y, m, d] = demo.scheduled_on.slice(0, 10).split("-").map(Number);
  const date = new Date(Date.UTC(y!, m! - 1, d!));
  const day = `${DAYS[date.getUTCDay()]}, ${String(d).padStart(2, "0")} ${MONTHS[m! - 1]} ${y}`;
  let time = "To be confirmed";
  if (demo.scheduled_time) {
    const [h, min] = demo.scheduled_time.slice(0, 5).split(":").map(Number);
    time = `${String(((h! + 11) % 12) + 1).padStart(2, "0")}:${String(min).padStart(2, "0")} ${h! < 12 ? "AM" : "PM"}`;
  }
  return `${day} · ${time}`;
}

type DemoForMessage = Demo & { lead_name?: string; canonical_student_name?: string | null; lead_grade?: string | null; lead_syllabus?: string | null; timezone?: string | null; duration_minutes?: number | null; matching_grade?: string | null; matching_syllabus?: string | null; matching_medium?: string | null };

export function demoParentMessage(demo: DemoForMessage, business: Business): string {
  const rows: [string, unknown][] = [
    ["Student", pipelineName(demo as unknown as Record<string, unknown>, "lead_name")],
    ["Subject", demo.subject_name],
    ["Date & time", `${demoWhen(demo)} (${demo.timezone || business.timezone})`],
    ["Teacher", demo.teacher_name],
    ["Duration", demo.duration_minutes ? `${demo.duration_minutes} minutes` : null],
    ["Meeting link", demo.meet_link],
  ];
  return `Dear Parent,\nThe demo class is scheduled.\n\n${lines(rows)}\n\nPlease join on time. Reply here if you need to change the time.\n— ${business.name}`;
}

export function demoTeacherMessage(demo: DemoForMessage, business: Business): string {
  const rows: [string, unknown][] = [
    ["Student", pipelineName(demo as unknown as Record<string, unknown>, "lead_name")],
    ["Class / syllabus", `${demo.matching_grade ?? demo.lead_grade ?? ""} ${demo.matching_syllabus ?? demo.lead_syllabus ?? ""}`.trim()],
    ["Subject", demo.subject_name],
    ["Medium", demo.matching_medium],
    ["Date & time", `${demoWhen(demo)} (${demo.timezone || business.timezone})`],
    ["Duration", demo.duration_minutes ? `${demo.duration_minutes} minutes` : null],
    ["Meeting link", demo.meet_link],
  ];
  return `Hi ${demo.teacher_name ?? "Teacher"},\nA demo class is booked with you.\n\n${lines(rows)}\n\nPlease confirm.\n— ${business.name}`;
}

/** Registration summary that the sales person forwards to the admin group. */
export function joinedMessage(lead: Lead & { canonical_student_code?: string | null }, business: Business): string {
  const rows: [string, unknown][] = [
    ["Student", pipelineName(lead as unknown as Record<string, unknown>)],
    ["Student code", lead.canonical_student_code ?? lead.admission_code],
    ["Admission code", lead.admission_code],
    ["Joined on", lead.joined_on],
    ["Class / grade", lead.grade],
    ["Syllabus", lead.syllabus],
    ["Subject", lead.subject],
    ["Parent phone", lead.phone],
    ["Student WhatsApp", lead.student_phone],
    ["Source", lead.source],
    ["Sales owner", lead.assigned_name],
    ["Learning needs", lead.learning_requirement],
    ["Parent concerns", lead.concern],
  ];
  return `NEW ADMISSION — ${business.name}\n\n${lines(rows)}`;
}

export function teacherProfileMessage(teacher: Teacher, business: Business): string {
  const rows: [string, unknown][] = [
    ["Teacher", teacher.name],
    ["Code", teacher.code],
    ["Category", teacher.category],
    ["Levels", teacher.levels],
    ["Subjects", teacher.subjects],
    ["Classes / syllabus", teacher.classes],
    ["Teaching medium", teacher.mediums],
    ["Qualification", teacher.qualification],
  ];
  return `Teacher profile — ${business.name}\n\n${lines(rows)}`;
}

/** SalesPipeline::whatsappMessage: a chat link with text, or a bare share link to choose a chat. */
export function whatsappMessage(chatLink: string | null, text: string): string {
  return `${chatLink || "https://wa.me/"}?text=${encodeURIComponent(text)}`;
}
