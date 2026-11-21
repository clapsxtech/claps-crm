import { MessageCircle, Phone } from "lucide-react";
import type { Demo, Unit } from "../lib/api";
import { whatsappLink } from "../lib/format";
import { demoParentMessage, demoTeacherMessage, whatsappMessage } from "../lib/messages";
import { cx } from "./ui";

const dialable = (phone: string, country?: string | null) => {
  const digits = phone.replace(/\D/g, "");
  return digits.length === 10 && (!country || country.toUpperCase() === "IN") ? `+91${digits}` : phone.replace(/[^0-9+]/g, "");
};

/** Call and WhatsApp buttons for the parent and the teacher of a demo; WhatsApp opens with the demo details. */
export function DemoContacts({ demo, unit, parentPhone, parentCountry, studentName, compact }: { demo: Demo; unit: Unit; parentPhone?: string | null; parentCountry?: string | null; studentName?: string; compact?: boolean }) {
  const business = { name: unit.name, timezone: unit.timezone };
  const record = { ...demo, lead_name: studentName ?? demo.lead_name ?? "" };
  const parentWa = parentPhone ? whatsappLink(parentPhone, parentCountry) : null;
  const teacherWa = demo.teacher_phone ? whatsappLink(demo.teacher_phone, "IN") : null;
  const base = cx("inline-flex items-center gap-1.5 rounded-lg font-semibold", compact ? "px-2 py-1 text-[11px]" : "px-2.5 py-1.5 text-xs");
  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center gap-1.5">
        <span className="w-14 text-[11px] font-semibold uppercase tracking-wider text-brand-muted">Parent</span>
        {parentPhone ? <a href={`tel:${dialable(parentPhone, parentCountry)}`} className={cx(base, "border border-brand-border bg-white text-brand-soft hover:bg-brand-fill")}><Phone className="size-3.5" />Call</a> : <span className="text-xs text-brand-muted">No number</span>}
        {parentWa && <a href={whatsappMessage(parentWa, demoParentMessage(record, business))} target="_blank" rel="noreferrer" className={cx(base, "bg-emerald-600 text-white hover:bg-emerald-700")}><MessageCircle className="size-3.5" />Send demo details</a>}
      </div>
      <div className="flex flex-wrap items-center gap-1.5">
        <span className="w-14 text-[11px] font-semibold uppercase tracking-wider text-brand-muted">Teacher</span>
        {demo.teacher_phone ? <a href={`tel:${dialable(demo.teacher_phone, "IN")}`} className={cx(base, "border border-brand-border bg-white text-brand-soft hover:bg-brand-fill")}><Phone className="size-3.5" />Call</a> : <span className="text-xs text-brand-muted">{demo.teacher_name ? "No number on file" : "Not assigned"}</span>}
        {teacherWa && <a href={whatsappMessage(teacherWa, demoTeacherMessage(record, business))} target="_blank" rel="noreferrer" className={cx(base, "bg-emerald-600 text-white hover:bg-emerald-700")}><MessageCircle className="size-3.5" />Send demo details</a>}
      </div>
    </div>
  );
}
