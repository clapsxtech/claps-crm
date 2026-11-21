import { useMemo, useState, type FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { ArrowLeft, BookOpen, Calendar, Check, Globe, GraduationCap, Mail, Megaphone, Phone, Star, User, UserPlus } from "lucide-react";
import { api, ApiError, query, unitPath, type Lead, type Page } from "../lib/api";
import { useUnit } from "../lib/session";
import { COUNTRIES, leadName, STAGES, todayIn } from "../lib/format";
import { Button, Card, cx, ErrorNote, Field, Input, Pill, Select, Textarea, useToast } from "../components/ui";

const GRADES = ["LKG", "UKG", ...Array.from({ length: 12 }, (_, i) => `Grade ${i + 1}`)];
const SYLLABI = ["CBSE", "ICSE", "State", "IGCSE", "IB", "Other"];
const SOURCES = ["Website", "Referral", "Facebook", "Instagram", "Google", "WhatsApp", "Walk-in", "Other"];

export function NewEnquiry() {
  const unit = useUnit();
  const toast = useToast();
  const navigate = useNavigate();
  const [step, setStep] = useState<1 | 2>(1);
  const [country, setCountry] = useState("IN");
  const [number, setNumber] = useState("");
  const [matches, setMatches] = useState<Lead[] | null>(null);
  const [separate, setSeparate] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<Record<string, string[]>>({});
  const [form, setForm] = useState({ name: "", grade: "", subject: "", syllabus: "", source: "", email: "", notes: "", enquiry_date: todayIn(unit.timezone), is_hot: false });
  const dial = COUNTRIES.find((c) => c.code === country)?.dial ?? "";
  const digits = number.replace(/\D/g, "");
  const phone = `${dial} ${digits}`;
  const set = (key: keyof typeof form) => (event: { target: { value: string } }) => setForm((current) => ({ ...current, [key]: event.target.value }));
  const validNumber = useMemo(() => digits.length >= (country === "IN" ? 10 : 6) && digits.length <= 14, [digits, country]);

  async function check(event: FormEvent) {
    event.preventDefault();
    if (!validNumber) { setError(country === "IN" ? "Enter the 10-digit mobile number." : "Enter a valid phone number."); return; }
    setBusy(true); setError(null);
    try {
      const tail = digits.slice(-10);
      const found = await api<Page<Lead>>(unitPath(unit.id, `/leads${query({ q: tail })}`));
      setMatches(found.data);
      setSeparate(false);
      if (found.data.length === 0) setStep(2);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function reEnquire(lead: Lead) {
    setBusy(true); setError(null);
    try {
      await api(unitPath(unit.id, "/actions/create-lead"), { body: { phone, country, existing_lead_id: lead.id, enquiry_date: todayIn(unit.timezone), source: form.source || undefined } });
      toast("success", `Re-enquiry logged on ${leadName(lead)}.`);
      navigate(`/leads/${lead.id}`);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    setBusy(true); setError(null); setFields({});
    try {
      const body = { phone, country, ...Object.fromEntries(Object.entries(form).filter(([, value]) => value !== "" && value !== false)), ...(separate ? { create_separate: true } : {}) };
      const { result } = await api<{ result: Lead }>(unitPath(unit.id, "/actions/create-lead"), { body });
      toast("success", `${leadName(result)} added to the pipeline.`);
      navigate(`/leads/${result.id}`);
    } catch (err) {
      if (err instanceof ApiError) { setFields(err.fields); setError(err.message); } else setError("Could not save the enquiry.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-xl">
      <Link to="/leads" className="mb-4 inline-flex items-center gap-2 text-sm font-medium text-brand-muted hover:text-brand-ink"><ArrowLeft className="size-4" />Back to leads</Link>
      <ol className="mb-5 flex items-center gap-3 text-sm" aria-label="Steps">
        <StepDot n={1} label="Verify number" state={step === 1 ? "current" : "done"} />
        <span className="h-px flex-1 bg-brand-border" aria-hidden />
        <StepDot n={2} label="Add details" state={step === 2 ? "current" : "todo"} />
      </ol>

      {step === 1 ? (
        <form onSubmit={check} className="space-y-4">
          <Card className="p-5 sm:p-6">
            <h2 className="text-xl font-semibold">New enquiry</h2>
            <p className="mt-1 text-sm text-brand-muted">Start with the WhatsApp number to check whether this lead is new.</p>
            <div className="mt-5 space-y-4">
              {error && <ErrorNote message={error} />}
              <Field label="Country" icon={Globe}>{(id) => (
                <Select id={id} withIcon value={country} onChange={(e) => setCountry(e.target.value)}>
                  {COUNTRIES.map((c) => <option key={c.code} value={c.code}>{c.name} ({c.dial})</option>)}
                </Select>
              )}</Field>
              <Field label="WhatsApp number" icon={Phone} required>{(id) => (
                <Input id={id} type="tel" inputMode="tel" autoComplete="off" withIcon required autoFocus value={number} placeholder="98765 43210"
                  onChange={(e) => { setNumber(e.target.value); setMatches(null); }} />
              )}</Field>
            </div>
          </Card>

          {matches && matches.length > 0 && (
            <Card className="p-5">
              <p className="font-semibold">This number is already in the pipeline</p>
              <p className="mt-1 text-sm text-brand-muted">Log a re-enquiry on the existing lead, or continue if this is a different student (for example a sibling).</p>
              <ul className="mt-4 space-y-2">
                {matches.map((lead) => (
                  <li key={lead.id} className="flex flex-col gap-3 rounded-xl border border-brand-border p-3 sm:flex-row sm:items-center">
                    <div className="min-w-0 flex-1">
                      <p className="truncate font-medium">{leadName(lead)} <span className="text-xs text-brand-muted">#{lead.id}</span></p>
                      <p className="text-xs text-brand-muted">{lead.phone} · {[lead.grade, lead.subject].filter(Boolean).join(" · ") || "No course yet"}</p>
                    </div>
                    <Pill className={STAGES[lead.crm_stage]?.className ?? STAGES.new.className}>{STAGES[lead.crm_stage]?.label}</Pill>
                    <div className="flex gap-2">
                      <Link to={`/leads/${lead.id}`} className="inline-flex h-8 items-center rounded-xl border border-brand-border px-3 text-xs font-semibold">Open</Link>
                      <Button size="sm" variant="primary" busy={busy} onClick={() => void reEnquire(lead)}>Log re-enquiry</Button>
                    </div>
                  </li>
                ))}
              </ul>
              <Button className="mt-4 w-full" icon={UserPlus} onClick={() => { setSeparate(true); setStep(2); }}>It’s a different student</Button>
            </Card>
          )}

          {!(matches && matches.length > 0) && <Button type="submit" variant="dark" size="lg" icon={Phone} className="w-full" busy={busy}>Check number</Button>}
        </form>
      ) : (
        <form onSubmit={save} className="space-y-4">
          <Card className="p-5 sm:p-6">
            <h2 className="text-xl font-semibold">New enquiry</h2>
            <p className="mt-1 text-sm text-brand-muted">{separate ? "Saving as a separate student with the same number." : "Number verified. Add the remaining details."}</p>
            <div className="mt-5 flex items-center gap-3 rounded-2xl border border-brand-border bg-brand-fill p-3">
              <div className="flex size-10 items-center justify-center rounded-xl border border-brand-border bg-white"><Phone className="size-4" /></div>
              <div className="flex-1"><p className="field-label">WhatsApp number</p><p className="font-semibold">{phone}</p></div>
              <button type="button" onClick={() => { setStep(1); setMatches(null); }} className="rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-semibold text-brand-primary">Change</button>
            </div>
            <div className="mt-5 space-y-4">
              {error && <ErrorNote message={error} />}
              <Field label="Student name" icon={User} hint="Optional now. It is required when the student registers." error={fields.name?.[0]}>{(id) => <Input id={id} withIcon value={form.name} onChange={set("name")} placeholder="Student's full name" maxLength={255} />}</Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Class / grade" icon={GraduationCap} error={fields.grade?.[0]}>{(id) => <><Input id={id} withIcon list="grades" value={form.grade} onChange={set("grade")} placeholder="Grade 8" maxLength={100} /><datalist id="grades">{GRADES.map((g) => <option key={g} value={g} />)}</datalist></>}</Field>
                <Field label="Subject" icon={BookOpen} error={fields.subject?.[0]}>{(id) => <Input id={id} withIcon value={form.subject} onChange={set("subject")} placeholder="Mathematics" maxLength={100} />}</Field>
                <Field label="Syllabus" icon={BookOpen} error={fields.syllabus?.[0]}>{(id) => <><Input id={id} withIcon list="syllabi" value={form.syllabus} onChange={set("syllabus")} placeholder="CBSE" maxLength={100} /><datalist id="syllabi">{SYLLABI.map((s) => <option key={s} value={s} />)}</datalist></>}</Field>
                <Field label="Source" icon={Megaphone} error={fields.source?.[0]}>{(id) => <><Input id={id} withIcon list="sources" value={form.source} onChange={set("source")} placeholder="Website" maxLength={100} /><datalist id="sources">{SOURCES.map((s) => <option key={s} value={s} />)}</datalist></>}</Field>
                <Field label="Parent email" icon={Mail} error={fields.email?.[0]}>{(id) => <Input id={id} type="email" withIcon value={form.email} onChange={set("email")} placeholder="Optional" maxLength={255} />}</Field>
                <Field label="Enquiry date" icon={Calendar} error={fields.enquiry_date?.[0]}>{(id) => <Input id={id} type="date" withIcon value={form.enquiry_date} onChange={set("enquiry_date")} max={todayIn(unit.timezone)} />}</Field>
              </div>
              <Field label="Notes" error={fields.notes?.[0]}>{(id) => <Textarea id={id} value={form.notes} onChange={set("notes")} placeholder="What did the parent ask for?" maxLength={4000} />}</Field>
              <label className="flex cursor-pointer items-center justify-between rounded-2xl border border-brand-border p-4">
                <span><span className="flex items-center gap-2 font-medium"><Star className="size-4 text-orange-500" />Hot lead</span><span className="text-xs text-brand-muted">Keen to start soon — keep at the top of the list.</span></span>
                <input type="checkbox" className="size-5 accent-orange-500" checked={form.is_hot} onChange={(e) => setForm((c) => ({ ...c, is_hot: e.target.checked }))} />
              </label>
            </div>
          </Card>
          <Button type="submit" variant="primary" size="lg" icon={Check} className="w-full" busy={busy}>Add to pipeline</Button>
        </form>
      )}
    </div>
  );
}

function StepDot({ n, label, state }: { n: number; label: string; state: "current" | "done" | "todo" }) {
  return (
    <li className="flex items-center gap-2">
      <span className={cx("flex size-7 items-center justify-center rounded-full text-xs font-semibold", state === "done" ? "bg-brand-success text-white" : state === "current" ? "bg-brand-ink text-white" : "bg-slate-200 text-brand-muted")}>{state === "done" ? <Check className="size-4" /> : n}</span>
      <span className={cx("font-semibold", state === "todo" && "text-brand-muted")}>{label}</span>
    </li>
  );
}
