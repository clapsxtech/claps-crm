import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import { Link, useParams } from "react-router-dom";
import { ArrowLeft, ArrowLeftRight, Bell, BookOpen, Calendar, CheckCircle2, Clock, Flame, GraduationCap, History, IndianRupee, Link2, MessageCircle, MessageSquarePlus, Pencil, Phone, RotateCcw, Search, Send, User, Users, Video, XCircle } from "lucide-react";
import { api, ApiError, query, unitPath, type Demo, type LeadDetail as Detail, type Teacher, type TeacherPage, type Unit } from "../lib/api";
import { joinedMessage, whatsappMessage } from "../lib/messages";
import { DemoContacts } from "../components/DemoContacts";
import { useSession, useUnit } from "../lib/session";
import { useApi } from "../lib/useApi";
import { DEMO_STATUS, formatDate, formatDateTime, formatTime, leadName, relativeDay, STAGES, todayIn, truthy, whatsappLink } from "../lib/format";
import { Button, Card, cx, EmptyState, ErrorNote, Field, Input, Modal, Pill, Select, Spinner, Textarea, useToast } from "../components/ui";

type ModalKind = null | "followup" | "reminder" | "demo" | "edit" | "lost" | "convert" | "transfer";

export function LeadDetail() {
  const { id } = useParams();
  const unit = useUnit();
  const { can, user } = useSession();
  const toast = useToast();
  const detail = useApi<Detail>(unitPath(unit.id, `/leads/${Number(id)}`));
  const [modal, setModal] = useState<ModalKind>(null);
  const today = todayIn(unit.timezone);

  async function act(action: string, body: Record<string, unknown>, success: string): Promise<boolean> {
    try {
      await api(unitPath(unit.id, `/actions/${action}`), { body });
      toast("success", success);
      detail.reload();
      return true;
    } catch (error) {
      toast("error", (error as Error).message);
      return false;
    }
  }

  if (detail.loading && !detail.data) return <Spinner label="Loading lead" />;
  if (detail.error) return <div className="space-y-4"><BackLink /><ErrorNote message={detail.error.status === 404 ? "This lead does not exist or is not assigned to you." : detail.error.message} onRetry={detail.error.status === 404 ? undefined : detail.reload} /></div>;
  if (!detail.data) return null;
  const { lead, contact, activities, demos, reminders, transfers } = detail.data;
  const isLead = lead.status === "LEAD";
  const wa = contact.whatsapp ?? whatsappLink(lead.phone, lead.country);
  const pendingReminders = reminders.filter((r) => !truthy(r.is_completed));
  const close = () => setModal(null);

  return (
    <div className="space-y-5">
      <BackLink />
      <Card className="p-5 sm:p-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="text-2xl font-bold tracking-tight">{leadName(lead)}</h2>
              {truthy(lead.is_hot) && <Pill className="bg-orange-50 text-orange-700 ring-orange-200"><Flame className="size-3" />Hot</Pill>}
              <Pill className={STAGES[lead.crm_stage]?.className ?? STAGES.new.className}>{STAGES[lead.crm_stage]?.label}</Pill>
            </div>
            <p className="mt-1 text-sm text-brand-muted">{[lead.grade, lead.subject, lead.syllabus].filter(Boolean).join(" · ") || "Course not recorded yet"}</p>
            {lead.status === "JOINED" && (
              <div className="mt-3 flex flex-col gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 sm:flex-row sm:items-center">
                <p className="flex-1 text-sm font-medium text-emerald-800">Registered {formatDate(lead.joined_on, true)}{lead.admission_code ? ` · ${lead.admission_code}` : ""}</p>
                <a href={whatsappMessage(null, joinedMessage(lead, { name: unit.name, timezone: unit.timezone }))} target="_blank" rel="noreferrer"
                  className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                  <Send className="size-4" />Send joined details
                </a>
              </div>
            )}
            {lead.status === "JOINED" && <p className="mt-1 text-xs text-brand-muted">Opens WhatsApp with the admission summary; choose the admin group to forward it.</p>}
            {lead.status === "LOST" && <p className="mt-2 text-sm font-medium text-rose-700">Lost{lead.lost_reason ? `: ${lead.lost_reason}` : ""}</p>}
          </div>
          <div className="flex gap-2">
            <a href={`tel:${contact.dial || lead.phone}`} className="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-white px-4 text-sm font-semibold hover:bg-brand-fill"><Phone className="size-4" />Call</a>
            {wa && <a href={wa} target="_blank" rel="noreferrer" className="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700"><MessageCircle className="size-4" />WhatsApp</a>}
          </div>
        </div>
        <dl className="mt-5 grid grid-cols-2 gap-x-4 gap-y-3 border-t border-brand-border pt-5 text-sm sm:grid-cols-4">
          <Info label="Phone" value={lead.phone} />
          <Info label="Next follow-up" value={isLead ? relativeDay(lead.next_follow_up_on, today) : "—"} tone={isLead && lead.next_follow_up_on && lead.next_follow_up_on < today ? "danger" : undefined} />
          <Info label="Owner" value={lead.assigned_name || "Unassigned"} />
          <Info label="Enquired" value={`${formatDate(lead.enquiry_date, true)}${lead.source ? ` · ${lead.source}` : ""}`} />
          {lead.email && <Info label="Email" value={lead.email} />}
          {lead.interest_status && <Info label="Interest" value={lead.interest_status} />}
          {lead.student_phone && <Info label="Student phone" value={lead.student_phone} />}
        </dl>
        {lead.notes && <p className="mt-4 whitespace-pre-line rounded-xl bg-brand-fill p-3 text-sm text-brand-soft">{lead.notes}</p>}
        <div className="mt-5 flex flex-wrap gap-2">
          {isLead && can("followup") && <Button variant="primary" icon={MessageSquarePlus} onClick={() => setModal("followup")}>Log follow-up</Button>}
          {isLead && can("schedule-demo") && <Button icon={Video} onClick={() => setModal("demo")}>Schedule demo</Button>}
          {isLead && <Link to={`/teachers?for_lead=${lead.id}`} className="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-white px-4 text-sm font-semibold hover:bg-brand-fill"><Users className="size-4" />Find teacher</Link>}
          {can("reminder") && <Button icon={Bell} onClick={() => setModal("reminder")}>Add reminder</Button>}
          {isLead && can("convert") && <Button icon={GraduationCap} onClick={() => setModal("convert")}>Register student</Button>}
          {can("update-lead") && <Button variant="ghost" icon={Pencil} onClick={() => setModal("edit")}>Edit</Button>}
          {isLead && can("transfer") && <Button variant="ghost" icon={ArrowLeftRight} onClick={() => setModal("transfer")}>Transfer</Button>}
          {isLead && can("update-lead") && <Button variant="ghost" icon={XCircle} className="text-brand-danger" onClick={() => setModal("lost")}>Mark lost</Button>}
          {lead.status === "LOST" && can("update-lead") && <Button icon={RotateCcw} onClick={() => void act("update-lead", { lead_id: lead.id, status: "LEAD" }, "Lead reopened.")}>Reopen</Button>}
        </div>
      </Card>

      {transfers.length > 0 && (
        <Card className="border-amber-200 bg-amber-50 p-4">
          {transfers.map((transfer) => (
            <div key={transfer.id} className="flex flex-col gap-2 sm:flex-row sm:items-center">
              <p className="flex-1 text-sm text-amber-900"><ArrowLeftRight className="mr-1 inline size-4" />Transfer from <b>{transfer.sender_name}</b> to <b>{transfer.recipient_name}</b> is pending.</p>
              {can("resolve-transfer") && (transfer.to_user_id === user?.id ? (
                <div className="flex gap-2">
                  <Button size="sm" variant="primary" onClick={() => void act("resolve-transfer", { transfer_id: transfer.id, status: "accepted" }, "Transfer accepted.")}>Accept</Button>
                  <Button size="sm" onClick={() => void act("resolve-transfer", { transfer_id: transfer.id, status: "rejected" }, "Transfer declined.")}>Decline</Button>
                </div>
              ) : <Button size="sm" onClick={() => void act("resolve-transfer", { transfer_id: transfer.id, status: "cancelled" }, "Transfer cancelled.")}>Cancel transfer</Button>)}
            </div>
          ))}
        </Card>
      )}

      <div className="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
        <Card className="p-5">
          <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold"><History className="size-5 text-brand-muted" />Activity</h3>
          {activities.length === 0 ? <p className="text-sm text-brand-muted">No activity yet.</p> : (
            <ol className="relative space-y-4 border-l border-brand-border pl-5">
              {activities.map((activity) => (
                <li key={activity.id} className="relative">
                  <span className="absolute -left-[26px] top-1.5 size-2.5 rounded-full border-2 border-white bg-brand-primary ring-1 ring-brand-border" aria-hidden />
                  <p className="text-sm font-medium">{activity.summary}</p>
                  <p className="text-xs text-brand-muted">{[activity.channel, activity.outcome].filter(Boolean).join(" · ")}{(activity.channel || activity.outcome) ? " — " : ""}{activity.actor_name ?? "System"} · {formatDateTime(activity.occurred_at)}</p>
                </li>
              ))}
            </ol>
          )}
        </Card>

        <div className="space-y-5">
          <Card className="p-5">
            <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold"><Video className="size-5 text-brand-muted" />Demos</h3>
            {demos.length === 0 ? <p className="text-sm text-brand-muted">No demos yet.</p> : <ul className="space-y-3">{demos.map((demo) => <DemoItem key={demo.id} demo={demo} unit={unit} parentPhone={lead.phone} parentCountry={lead.country} studentName={leadName(lead)} today={today} canUpdate={can("update-demo")} onAct={act} />)}</ul>}
          </Card>
          <Card className="p-5">
            <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold"><Bell className="size-5 text-brand-muted" />Reminders</h3>
            {pendingReminders.length === 0 ? <p className="text-sm text-brand-muted">No open reminders.</p> : (
              <ul className="space-y-2">
                {pendingReminders.map((reminder) => (
                  <li key={reminder.id} className="flex items-start gap-3 rounded-xl border border-brand-border p-3">
                    <div className="min-w-0 flex-1"><p className="text-sm font-medium">{reminder.text}</p><p className={cx("text-xs", reminder.due_on < today ? "text-brand-danger" : "text-brand-muted")}>{relativeDay(reminder.due_on, today)}{reminder.due_time ? ` · ${formatTime(reminder.due_time)}` : ""}</p></div>
                    {can("complete-reminder") && <button type="button" onClick={() => void act("complete-reminder", { reminder_id: reminder.id }, "Reminder completed.")} className="rounded-lg p-1.5 text-brand-success hover:bg-emerald-50" aria-label="Mark reminder done" title="Done"><CheckCircle2 className="size-5" /></button>}
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      </div>

      <FollowupModal open={modal === "followup"} onClose={close} name={leadName(lead)} onSave={(body) => act("followup", { lead_id: lead.id, ...body }, "Follow-up logged.")} />
      <ReminderModal open={modal === "reminder"} onClose={close} name={leadName(lead)} today={today} onSave={(body) => act("reminder", { lead_id: lead.id, ...body }, "Reminder added.")} />
      <DemoModal open={modal === "demo"} onClose={close} name={leadName(lead)} today={today} onSave={(body) => act("schedule-demo", { lead_id: lead.id, ...body }, "Demo scheduled.")} />
      <EditModal open={modal === "edit"} onClose={close} lead={lead} onSave={async () => { toast("success", "Lead updated."); detail.reload(); return true; }} />
      <LostModal open={modal === "lost"} onClose={close} name={leadName(lead)} onSave={(reason) => act("update-lead", { lead_id: lead.id, status: "LOST", lost_reason: reason }, "Lead marked as lost.")} />
      <ConvertModal open={modal === "convert"} onClose={close} lead={lead} today={today} onSave={(body) => act("convert", { lead_id: lead.id, ...body }, "Student registered.")} />
      <TransferModal open={modal === "transfer"} onClose={close} name={leadName(lead)} currentOwner={lead.assigned_to} onSave={(to) => act("transfer", { lead_id: lead.id, to_user_id: to }, "Transfer requested.")} />
    </div>
  );
}

function BackLink() {
  return <Link to="/leads" className="inline-flex items-center gap-2 text-sm font-medium text-brand-muted hover:text-brand-ink"><ArrowLeft className="size-4" />Back to leads</Link>;
}

function Info({ label, value, tone }: { label: string; value: ReactNode; tone?: "danger" }) {
  return <div className="min-w-0"><dt className="field-label">{label}</dt><dd className={cx("mt-0.5 truncate font-medium", tone === "danger" && "text-brand-danger")}>{value}</dd></div>;
}

function DemoItem({ demo, unit, parentPhone, parentCountry, studentName, today, canUpdate, onAct }: { demo: Demo; unit: Unit; parentPhone: string; parentCountry: string | null; studentName: string; today: string; canUpdate: boolean; onAct: (action: string, body: Record<string, unknown>, success: string) => Promise<boolean> }) {
  const open = demo.status === "SCHEDULED" || demo.status === "RESCHEDULED";
  const ended = demo.ends_at ? new Date(demo.ends_at.replace(" ", "T") + "Z").getTime() < Date.now() : demo.scheduled_on <= today;
  const info = DEMO_STATUS[demo.status] ?? DEMO_STATUS.SCHEDULED;
  return (
    <li className="rounded-xl border border-brand-border p-3">
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="text-sm font-semibold">{relativeDay(demo.scheduled_on, today)} · {formatTime(demo.scheduled_time)}</p>
          <p className="mt-1 text-base font-semibold text-brand-ink">{demo.teacher_name ?? <span className="font-medium text-brand-muted">Teacher to be confirmed</span>}</p>
          {demo.subject_name && <p className="text-sm text-brand-muted">{demo.subject_name}</p>}
        </div>
        <Pill className={info.className}>{info.label}</Pill>
      </div>
      {demo.meet_link && <a href={demo.meet_link} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-primary"><Link2 className="size-3.5" />Join link</a>}
      {open && <div className="mt-3 border-t border-brand-border pt-3"><DemoContacts demo={demo} unit={unit} parentPhone={parentPhone} parentCountry={parentCountry} studentName={studentName} compact /></div>}
      {open && canUpdate && (
        <div className="mt-3 flex flex-wrap gap-2">
          {ended && <Button size="sm" variant="primary" onClick={() => void onAct("update-demo", { demo_id: demo.id, status: "ATTENDED" }, "Demo marked as attended.")}>Attended</Button>}
          {ended && <Button size="sm" onClick={() => void onAct("update-demo", { demo_id: demo.id, status: "NO_SHOW" }, "Demo marked as no-show.")}>No show</Button>}
          <Button size="sm" variant="danger" onClick={() => void onAct("update-demo", { demo_id: demo.id, status: "CANCELLED" }, "Demo cancelled.")}>Cancel</Button>
        </div>
      )}
    </li>
  );
}

function useSubmit(onSave: () => Promise<boolean>, onClose: () => void) {
  const [busy, setBusy] = useState(false);
  return { busy, submit: async (event: FormEvent) => { event.preventDefault(); setBusy(true); try { if (await onSave()) onClose(); } finally { setBusy(false); } } };
}

const OUTCOMES = ["Interested", "Call back later", "No reply", "Not interested", "Demo requested"];

function FollowupModal({ open, onClose, name, onSave }: { open: boolean; onClose: () => void; name: string; onSave: (body: Record<string, unknown>) => Promise<boolean> }) {
  const [channel, setChannel] = useState("whatsapp");
  const [outcome, setOutcome] = useState("");
  const [note, setNote] = useState("");
  const [next, setNext] = useState("");
  useEffect(() => { if (open) { setOutcome(""); setNote(""); setNext(""); } }, [open]);
  const { busy, submit } = useSubmit(() => onSave({ channel, outcome, note, ...(next ? { next_due_on: next } : {}) }), onClose);
  return (
    <Modal open={open} onClose={onClose} title="Log follow-up" subtitle={name} icon={MessageSquarePlus}
      footer={<><Button onClick={onClose}>Cancel</Button><Button form="followup-form" type="submit" variant="primary" busy={busy} disabled={!outcome || !note}>Save follow-up</Button></>}>
      <form id="followup-form" onSubmit={submit} className="space-y-4">
        <Field label="Channel">{(id) => <Select id={id} value={channel} onChange={(e) => setChannel(e.target.value)}><option value="whatsapp">WhatsApp</option><option value="call">Call</option><option value="email">Email</option><option value="other">Other</option></Select>}</Field>
        <div className="space-y-1.5"><p className="field-label">Outcome<span className="text-brand-danger">*</span></p>
          <div className="flex flex-wrap gap-2">{OUTCOMES.map((value) => <button key={value} type="button" onClick={() => setOutcome(value)} aria-pressed={outcome === value} className={cx("rounded-full border px-3 py-1.5 text-sm font-medium", outcome === value ? "border-brand-primary bg-blue-50 text-brand-primary" : "border-brand-border bg-white text-brand-soft")}>{value}</button>)}</div>
          <Input aria-label="Outcome" value={outcome} onChange={(e) => setOutcome(e.target.value)} placeholder="Or type the outcome" maxLength={1000} />
        </div>
        <Field label="What was discussed" required>{(id) => <Textarea id={id} required value={note} onChange={(e) => setNote(e.target.value)} maxLength={4000} />}</Field>
        <Field label="Next follow-up" icon={Calendar} hint="Leave empty if no follow-up is planned.">{(id) => <Input id={id} type="date" withIcon value={next} onChange={(e) => setNext(e.target.value)} />}</Field>
      </form>
    </Modal>
  );
}

function ReminderModal({ open, onClose, name, today, onSave }: { open: boolean; onClose: () => void; name: string; today: string; onSave: (body: Record<string, unknown>) => Promise<boolean> }) {
  const [text, setText] = useState("");
  const [due, setDue] = useState(today);
  const [time, setTime] = useState("");
  useEffect(() => { if (open) { setText(""); setDue(today); setTime(""); } }, [open, today]);
  const { busy, submit } = useSubmit(() => onSave({ text, due_on: due, ...(time ? { due_time: time } : {}) }), onClose);
  return (
    <Modal open={open} onClose={onClose} title="Add reminder" subtitle={name} icon={Bell}
      footer={<><Button onClick={onClose}>Cancel</Button><Button form="reminder-form" type="submit" variant="primary" busy={busy} disabled={!text || !due}>Add reminder</Button></>}>
      <form id="reminder-form" onSubmit={submit} className="space-y-4">
        <Field label="Reminder" required>{(id) => <Input id={id} required value={text} onChange={(e) => setText(e.target.value)} placeholder="Send demo slots to parent" maxLength={500} />}</Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Date" icon={Calendar} required>{(id) => <Input id={id} type="date" withIcon required value={due} onChange={(e) => setDue(e.target.value)} />}</Field>
          <Field label="Time" icon={Clock}>{(id) => <Input id={id} type="time" withIcon value={time} onChange={(e) => setTime(e.target.value)} />}</Field>
        </div>
      </form>
    </Modal>
  );
}

function DemoModal({ open, onClose, name, today, onSave }: { open: boolean; onClose: () => void; name: string; today: string; onSave: (body: Record<string, unknown>) => Promise<boolean> }) {
  const unit = useUnit();
  const [date, setDate] = useState(today);
  const [time, setTime] = useState("");
  const [subject, setSubject] = useState("");
  const [teacherQuery, setTeacherQuery] = useState("");
  const [teacher, setTeacher] = useState<Teacher | null>(null);
  const [link, setLink] = useState("");
  const [notes, setNotes] = useState("");
  const [debounced, setDebounced] = useState("");
  useEffect(() => { if (open) { setDate(today); setTime(""); setSubject(""); setTeacher(null); setTeacherQuery(""); setLink(""); setNotes(""); } }, [open, today]);
  useEffect(() => { const t = setTimeout(() => setDebounced(teacherQuery), 300); return () => clearTimeout(t); }, [teacherQuery]);
  const teachers = useApi<TeacherPage>(open ? unitPath(unit.id, `/teachers${query({ teacher_q: debounced, subject_id: subject })}`) : null);
  const { busy, submit } = useSubmit(() => onSave({ scheduled_on: date, scheduled_time: time, ...(teacher ? { teacher_id: teacher.id } : {}), ...(subject ? { subject_id: Number(subject) } : {}), ...(link ? { meet_link: link } : {}), ...(notes ? { scheduling_notes: notes } : {}) }), onClose);
  return (
    <Modal open={open} onClose={onClose} title="Schedule demo" subtitle={name} icon={Video}
      footer={<><Button onClick={onClose}>Cancel</Button><Button form="demo-form" type="submit" variant="primary" busy={busy} disabled={!date || !time}>Book demo</Button></>}>
      <form id="demo-form" onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <Field label="Date" icon={Calendar} required>{(id) => <Input id={id} type="date" withIcon required min={today} value={date} onChange={(e) => setDate(e.target.value)} />}</Field>
          <Field label="Time" icon={Clock} required>{(id) => <Input id={id} type="time" withIcon required value={time} onChange={(e) => setTime(e.target.value)} />}</Field>
        </div>
        <Field label="Subject" icon={BookOpen}>{(id) => (
          <Select id={id} withIcon value={subject} onChange={(e) => { setSubject(e.target.value); setTeacher(null); }}>
            <option value="">Select subject</option>
            {teachers.data?.subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </Select>
        )}</Field>
        <div className="space-y-1.5">
          <p className="field-label">Teacher</p>
          {teacher ? (
            <div className="flex items-center justify-between rounded-xl border border-brand-primary bg-blue-50 px-3 py-3"><span><span className="block text-base font-semibold">{teacher.name}</span><span className="text-sm text-brand-muted">{teacher.code} · {teacher.tt_hours} TT h/week</span></span><button type="button" className="text-sm font-semibold text-brand-primary" onClick={() => setTeacher(null)}>Change</button></div>
          ) : (
            <>
              <div className="relative"><Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><Input aria-label="Search teachers" withIcon value={teacherQuery} onChange={(e) => setTeacherQuery(e.target.value)} placeholder="Search by name or CT code (optional)" /></div>
              <ul className="max-h-64 divide-y divide-brand-border overflow-y-auto rounded-xl border border-brand-border">
                {teachers.loading && !teachers.data ? <li className="p-3 text-sm text-brand-muted">Loading teachers…</li> :
                  (teachers.data?.data ?? []).slice(0, 12).map((t) => (
                    <li key={t.id}><button type="button" onClick={() => setTeacher(t)} className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-brand-fill">
                      <span className="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-blue-50 text-brand-primary"><span className="text-sm font-bold leading-none">{t.tt_hours}</span><span className="text-[9px] font-semibold uppercase">TT hrs</span></span>
                      <span className="min-w-0 flex-1"><span className="block truncate text-base font-semibold">{t.name}</span><span className="block truncate text-xs text-brand-muted">{[t.code, t.levels, t.category, t.subjects].filter(Boolean).join(" · ")}</span></span>
                    </button></li>
                  ))}
                {teachers.data && teachers.data.data.length === 0 && <li className="p-3 text-sm text-brand-muted">No teachers match.</li>}
              </ul>
            </>
          )}
        </div>
        <Field label="Meet link" icon={Link2}>{(id) => <Input id={id} type="url" withIcon value={link} onChange={(e) => setLink(e.target.value)} placeholder="https://meet.google.com/…" />}</Field>
        <Field label="Scheduling notes">{(id) => <Textarea id={id} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="E.g. student wants extra focus on algebra" maxLength={2000} />}</Field>
      </form>
    </Modal>
  );
}

function EditModal({ open, onClose, lead, onSave }: { open: boolean; onClose: () => void; lead: Detail["lead"]; onSave: (body: null) => Promise<boolean> }) {
  const unit = useUnit();
  const keys = ["name", "grade", "subject", "syllabus", "source", "email", "student_phone", "interest_status", "notes", "learning_requirement", "concern", "about_student", "about_parent"] as const;
  const initial = () => Object.fromEntries(keys.map((key) => [key, (lead[key] as string | null) ?? ""])) as Record<(typeof keys)[number], string>;
  const [form, setForm] = useState(initial);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  useEffect(() => { if (open) { setForm(initial()); setErrors({}); } }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  const set = (key: (typeof keys)[number]) => (event: { target: { value: string } }) => setForm((current) => ({ ...current, [key]: event.target.value }));
  const { busy, submit } = useSubmit(async () => {
    const changed = Object.fromEntries(keys.filter((key) => form[key] !== ((lead[key] as string | null) ?? "")).map((key) => [key, form[key] === "" ? null : form[key]]));
    if (Object.keys(changed).length === 0) return true;
    // Posted here (not through onSave) so field errors can be shown inline.
    try {
      await api(unitPath(unit.id, "/actions/update-lead"), { body: { lead_id: lead.id, ...changed } });
    } catch (error) {
      if (error instanceof ApiError && Object.keys(error.fields).length) { setErrors(error.fields); return false; }
      throw error;
    }
    return onSave(null);
  }, onClose);
  const text = (key: (typeof keys)[number], label: string, extra: Partial<Parameters<typeof Input>[0]> = {}) => (
    <Field label={label} error={errors[key]?.[0]}>{(id) => <Input id={id} value={form[key]} onChange={set(key)} {...extra} />}</Field>
  );
  return (
    <Modal open={open} onClose={onClose} title="Edit lead" subtitle={leadName(lead)} icon={Pencil}
      footer={<><Button onClick={onClose}>Cancel</Button><Button form="edit-form" type="submit" variant="primary" busy={busy}>Save changes</Button></>}>
      <form id="edit-form" onSubmit={submit} className="space-y-4">
        {text("name", "Student name", { maxLength: 255 })}
        <div className="grid grid-cols-2 gap-3">{text("grade", "Class / grade")}{text("subject", "Subject")}{text("syllabus", "Syllabus")}{text("source", "Source")}</div>
        <div className="grid grid-cols-2 gap-3">{text("email", "Parent email", { type: "email" })}{text("student_phone", "Student phone", { type: "tel" })}</div>
        {text("interest_status", "Interest", { maxLength: 30, placeholder: "e.g. Warm" })}
        {(["notes", "learning_requirement", "concern", "about_student", "about_parent"] as const).map((key) => (
          <Field key={key} label={{ notes: "Notes", learning_requirement: "Learning requirement", concern: "Concerns", about_student: "About the student", about_parent: "About the parent" }[key]} error={errors[key]?.[0]}>
            {(id) => <Textarea id={id} value={form[key]} onChange={set(key)} maxLength={4000} className="min-h-16" />}
          </Field>
        ))}
      </form>
    </Modal>
  );
}

function LostModal({ open, onClose, name, onSave }: { open: boolean; onClose: () => void; name: string; onSave: (reason: string) => Promise<boolean> }) {
  const [reason, setReason] = useState("");
  useEffect(() => { if (open) setReason(""); }, [open]);
  const { busy, submit } = useSubmit(() => onSave(reason), onClose);
  return (
    <Modal open={open} onClose={onClose} title="Mark as lost" subtitle={name} icon={XCircle}
      footer={<><Button onClick={onClose}>Cancel</Button><Button form="lost-form" type="submit" variant="danger" busy={busy} disabled={!reason.trim()}>Mark lost</Button></>}>
      <form id="lost-form" onSubmit={submit}>
        <Field label="Reason" required hint="This helps the team understand why enquiries do not convert.">{(id) => <Textarea id={id} required value={reason} onChange={(e) => setReason(e.target.value)} maxLength={2000} placeholder="Fees too high, chose another tutor…" />}</Field>
      </form>
    </Modal>
  );
}

function ConvertModal({ open, onClose, lead, today, onSave }: { open: boolean; onClose: () => void; lead: Detail["lead"]; today: string; onSave: (body: Record<string, unknown>) => Promise<boolean> }) {
  const [form, setForm] = useState({ student_name: "", joined_on: today, student_phone: "", guardian_contact: "", monthly_fee: "", fee_per_hour: "", payment_amount: "", payment_reference: "" });
  useEffect(() => { if (open) setForm({ student_name: lead.name ?? "", joined_on: today, student_phone: lead.student_phone ?? "", guardian_contact: lead.phone, monthly_fee: lead.monthly_fee_paise ? String(Number(lead.monthly_fee_paise) / 100) : "", fee_per_hour: "", payment_amount: "", payment_reference: "" }); }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  const set = (key: keyof typeof form) => (event: { target: { value: string } }) => setForm((current) => ({ ...current, [key]: event.target.value }));
  const paise = (rupees: string) => rupees.trim() === "" ? undefined : Math.round(Number(rupees) * 100);
  const invalidMoney = [form.monthly_fee, form.fee_per_hour, form.payment_amount].some((value) => value.trim() !== "" && !(Number(value) >= 0));
  const { busy, submit } = useSubmit(() => onSave({
    student_name: form.student_name.trim() || undefined, joined_on: form.joined_on,
    student_phone: form.student_phone || undefined, guardian_contact: form.guardian_contact || undefined,
    monthly_fee_paise: paise(form.monthly_fee), fee_per_hour_paise: paise(form.fee_per_hour),
    payment_amount_paise: paise(form.payment_amount), payment_reference: form.payment_reference || undefined,
  }), onClose);
  return (
    <Modal open={open} onClose={onClose} title="Register student" subtitle={leadName(lead)} icon={GraduationCap}
      footer={<><Button onClick={onClose}>Cancel</Button><Button form="convert-form" type="submit" variant="primary" busy={busy} disabled={!form.student_name.trim() || invalidMoney}>Register</Button></>}>
      <form id="convert-form" onSubmit={submit} className="space-y-4">
        <p className="rounded-xl bg-blue-50 p-3 text-sm text-blue-900">This creates the student record in the ERP and marks the enquiry as joined.</p>
        <Field label="Student name" icon={User} required>{(id) => <Input id={id} withIcon required value={form.student_name} onChange={set("student_name")} maxLength={255} />}</Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Joining date" icon={Calendar}>{(id) => <Input id={id} type="date" withIcon value={form.joined_on} onChange={set("joined_on")} />}</Field>
          <Field label="Student phone" icon={Phone}>{(id) => <Input id={id} type="tel" withIcon value={form.student_phone} onChange={set("student_phone")} />}</Field>
        </div>
        <Field label="Guardian contact">{(id) => <Input id={id} value={form.guardian_contact} onChange={set("guardian_contact")} maxLength={255} />}</Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Monthly fee (₹)" icon={IndianRupee}>{(id) => <Input id={id} inputMode="decimal" withIcon value={form.monthly_fee} onChange={set("monthly_fee")} />}</Field>
          <Field label="Fee per hour (₹)" icon={IndianRupee}>{(id) => <Input id={id} inputMode="decimal" withIcon value={form.fee_per_hour} onChange={set("fee_per_hour")} />}</Field>
          <Field label="First payment (₹)" icon={IndianRupee}>{(id) => <Input id={id} inputMode="decimal" withIcon value={form.payment_amount} onChange={set("payment_amount")} />}</Field>
          <Field label="Payment reference">{(id) => <Input id={id} value={form.payment_reference} onChange={set("payment_reference")} maxLength={120} />}</Field>
        </div>
        {invalidMoney && <p className="text-xs font-medium text-brand-danger">Amounts must be numbers.</p>}
      </form>
    </Modal>
  );
}

function TransferModal({ open, onClose, name, currentOwner, onSave }: { open: boolean; onClose: () => void; name: string; currentOwner: number | null; onSave: (to: number) => Promise<boolean> }) {
  const unit = useUnit();
  const members = useApi<{ data: { id: number; name: string }[] }>(open ? unitPath(unit.id, "/members") : null);
  const [to, setTo] = useState("");
  useEffect(() => { if (open) setTo(""); }, [open]);
  const { busy, submit } = useSubmit(() => onSave(Number(to)), onClose);
  return (
    <Modal open={open} onClose={onClose} title="Transfer lead" subtitle={name} icon={ArrowLeftRight}
      footer={<><Button onClick={onClose}>Cancel</Button><Button form="transfer-form" type="submit" variant="primary" busy={busy} disabled={!to}>Request transfer</Button></>}>
      <form id="transfer-form" onSubmit={submit} className="space-y-3">
        <p className="text-sm text-brand-muted">The new owner accepts the transfer before the lead moves to them.</p>
        {members.loading ? <Spinner /> : members.error ? <ErrorNote message={members.error.message} /> : members.data?.data.filter((m) => m.id !== currentOwner).length ? (
          <Field label="Transfer to" required>{(id) => (
            <Select id={id} required value={to} onChange={(e) => setTo(e.target.value)}>
              <option value="">Choose a team member</option>
              {members.data!.data.filter((m) => m.id !== currentOwner).map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
            </Select>
          )}</Field>
        ) : <EmptyState icon={User} title="No other team members">Ask a team manager to add sales staff in the ERP.</EmptyState>}
      </form>
    </Modal>
  );
}
