import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { ArrowLeft, GraduationCap, MessageCircle, Phone, Search, Send } from "lucide-react";
import { query, unitPath, type LeadDetail, type Teacher, type TeacherPage } from "../lib/api";
import { useUnit } from "../lib/session";
import { useApi } from "../lib/useApi";
import { leadName, whatsappLink } from "../lib/format";
import { teacherProfileMessage, whatsappMessage } from "../lib/messages";
import { PageHeader } from "../components/Layout";
import { Card, cx, EmptyState, ErrorNote, Pager, Pill, Select, Spinner } from "../components/ui";

const LEVELS = [{ value: "", label: "All levels" }, { value: "LP", label: "LP" }, { value: "UP", label: "UP" }, { value: "HS", label: "HS" }];
const SORTS = [{ value: "code", label: "CT code" }, { value: "registered_desc", label: "Registered (newest first)" }, { value: "registered_asc", label: "Registered (oldest first)" }];

export function Teachers() {
  const unit = useUnit();
  const [params, setParams] = useSearchParams();
  const level = params.get("level") ?? "";
  const category = params.get("category") ?? "";
  const subject = params.get("subject_id") ?? "";
  const sort = params.get("sort") ?? "code";
  const page = Number(params.get("page") ?? 1) || 1;
  const forLead = Number(params.get("for_lead") ?? 0) || null;
  const [search, setSearch] = useState(params.get("teacher_q") ?? "");

  function update(changes: Record<string, string | null>) {
    const next = new URLSearchParams(params);
    for (const [key, value] of Object.entries(changes)) value ? next.set(key, value) : next.delete(key);
    if (!("page" in changes)) next.delete("page");
    setParams(next, { replace: true });
  }
  useEffect(() => {
    const t = setTimeout(() => { if (search !== (params.get("teacher_q") ?? "")) update({ teacher_q: search || null }); }, 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  const teachers = useApi<TeacherPage>(unitPath(unit.id, `/teachers${query({ teacher_q: params.get("teacher_q"), level, category, subject_id: subject, sort: sort === "code" ? null : sort, teachers_page: page })}`));
  const lead = useApi<LeadDetail>(forLead ? unitPath(unit.id, `/leads/${forLead}`) : null);
  const parentWa = lead.data ? (lead.data.contact.whatsapp ?? whatsappLink(lead.data.lead.phone, lead.data.lead.country)) : null;
  const business = { name: unit.name, timezone: unit.timezone };

  return (
    <div>
      {forLead && <Link to={`/leads/${forLead}`} className="mb-3 inline-flex items-center gap-2 text-sm font-medium text-brand-muted hover:text-brand-ink"><ArrowLeft className="size-4" />Back to lead</Link>}
      <PageHeader title="Teacher directory" subtitle={lead.data ? `Choose a teacher and send the profile to ${leadName(lead.data.lead)}’s parent.` : "Find a teacher for a demo by level, category, subject and current load."} />

      <div role="tablist" aria-label="Teacher level" className="mb-3 flex gap-1 overflow-x-auto rounded-2xl border border-brand-border bg-white p-1">
        {LEVELS.map((option) => (
          <button key={option.value} type="button" role="tab" aria-selected={level === option.value} onClick={() => update({ level: option.value || null })}
            className={cx("whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold", level === option.value ? "bg-brand-ink text-white" : "text-brand-soft hover:bg-slate-100")}>
            {option.label}
          </button>
        ))}
      </div>

      <div className="mb-4 grid gap-3 md:grid-cols-[1fr_auto_auto_auto]">
        <label className="relative"><span className="sr-only">Search teachers</span><Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by name or CT code…" className="control h-12 rounded-2xl pl-11" /></label>
        <Select aria-label="Category" value={category} onChange={(e) => update({ category: e.target.value || null })} className="h-12 md:w-44">
          <option value="">All categories</option>{(teachers.data?.categories ?? ["Standard", "Advanced"]).map((c) => <option key={c} value={c}>{c}</option>)}
        </Select>
        <Select aria-label="Subject" value={subject} onChange={(e) => update({ subject_id: e.target.value || null })} className="h-12 md:w-48">
          <option value="">All subjects</option>{teachers.data?.subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </Select>
        <Select aria-label="Order" value={sort} onChange={(e) => update({ sort: e.target.value === "code" ? null : e.target.value })} className="h-12 md:w-56">
          {SORTS.map((s) => <option key={s.value} value={s.value}>Order: {s.label}</option>)}
        </Select>
      </div>

      {teachers.error ? <ErrorNote message={teachers.error.message} onRetry={teachers.reload} /> :
        teachers.loading && !teachers.data ? <Card><Spinner label="Loading teachers" /></Card> :
        teachers.data && teachers.data.data.length === 0 ? <Card><EmptyState icon={GraduationCap} title="No teachers match">Try another level, category or subject. Levels and categories are set by the academics team in the ERP.</EmptyState></Card> :
        teachers.data && (
          <>
            <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {teachers.data.data.map((teacher) => <TeacherCard key={teacher.id} teacher={teacher} profileLink={whatsappMessage(parentWa, teacherProfileMessage(teacher, business))} toParent={Boolean(parentWa)} />)}
            </ul>
            {teachers.data.page && <Pager page={teachers.data.page} lastPage={teachers.data.last_page ?? 1} total={teachers.data.total ?? 0} onPage={(value) => update({ page: String(value) })} />}
          </>
        )}
    </div>
  );
}

function TeacherCard({ teacher, profileLink, toParent }: { teacher: Teacher; profileLink: string; toParent: boolean }) {
  const wa = teacher.phone ? whatsappLink(teacher.phone, "IN") : null;
  const digits = (teacher.phone ?? "").replace(/\D/g, "");
  return (
    <li className="card flex flex-col p-4">
      <div className="flex items-start gap-3">
        <div className="flex size-16 shrink-0 flex-col items-center justify-center rounded-2xl bg-blue-50 text-brand-primary" title={`${teacher.tt_hours} timetable hours per week`}>
          <span className="text-xl font-bold leading-none">{teacher.tt_hours}</span>
          <span className="mt-1 text-[10px] font-semibold uppercase tracking-wider">TT hrs</span>
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-lg font-semibold leading-snug">{teacher.name}</p>
          <p className="text-sm font-medium text-brand-muted">{teacher.code}</p>
          <div className="mt-1.5 flex flex-wrap gap-1">
            {teacher.level_list.map((l) => <Pill key={l} className="bg-slate-100 text-slate-700 ring-slate-200">{l}</Pill>)}
            {teacher.category && <Pill className={teacher.category === "Advanced" ? "bg-violet-50 text-violet-700 ring-violet-200" : "bg-sky-50 text-sky-700 ring-sky-200"}>{teacher.category}</Pill>}
          </div>
        </div>
      </div>
      <dl className="mt-3 space-y-1.5 border-t border-brand-border pt-3 text-sm">
        <Row label="TT hours" value={`${teacher.tt_hours} h / week`} />
        <Row label="Subjects" value={teacher.subjects} />
        <Row label="Classes" value={teacher.classes} />
        <Row label="Medium" value={teacher.mediums} />
        <Row label="Qualification" value={teacher.qualification} />
      </dl>
      <div className="mt-auto flex flex-wrap gap-2 pt-3">
        <a href={profileLink} target="_blank" rel="noreferrer" className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
          <Send className="size-4" />{toParent ? "Send profile to parent" : "Share profile"}
        </a>
        {digits && <a href={`tel:${digits.length === 10 ? `+91${digits}` : teacher.phone}`} aria-label={`Call ${teacher.name}`} title="Call teacher" className="inline-flex size-10 items-center justify-center rounded-xl border border-brand-border bg-white text-brand-soft hover:bg-brand-fill"><Phone className="size-4" /></a>}
        {wa && <a href={wa} target="_blank" rel="noreferrer" aria-label={`WhatsApp ${teacher.name}`} title="WhatsApp teacher" className="inline-flex size-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"><MessageCircle className="size-4" /></a>}
      </div>
    </li>
  );
}

function Row({ label, value }: { label: string; value: string | null }) {
  if (!value) return null;
  return <div className="flex gap-2"><dt className="w-24 shrink-0 text-brand-muted">{label}</dt><dd className="min-w-0 font-medium">{value}</dd></div>;
}
