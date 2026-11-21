import { useState } from "react";
import { Link } from "react-router-dom";
import { ArrowRight, ArrowUpRight, Bell, CalendarDays, CheckCircle2, Flame, Plus, Users, Video } from "lucide-react";
import { query, unitPath, type Demo, type Lead, type Overview, type Page, type Reminder } from "../lib/api";
import { useSession, useUnit } from "../lib/session";
import { useApi } from "../lib/useApi";
import { formatTime, leadName, relativeDay, STAGES } from "../lib/format";
import { Card, cx, EmptyState, ErrorNote, Segmented, Spinner } from "../components/ui";

export function Home() {
  const unit = useUnit();
  const { user } = useSession();
  const overview = useApi<Overview>(unitPath(unit.id, "/overview"));
  const [tab, setTab] = useState<"today" | "overdue" | "reminders">("today");
  const followups = useApi<Page<Lead>>(tab === "reminders" ? null : unitPath(unit.id, `/leads${query({ followup: tab })}`));
  const reminders = useApi<Page<Reminder>>(tab === "reminders" ? unitPath(unit.id, "/reminders?due=1") : null);
  const demos = useApi<Page<Demo>>(unitPath(unit.id, "/demos?scope=upcoming"));
  const o = overview.data;
  const firstName = user?.name.split(/\s+/)[0] ?? "";
  const dateLabel = new Date().toLocaleDateString("en-IN", { weekday: "long", day: "numeric", month: "long" });

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-sm text-brand-muted">{dateLabel}</p>
          <h2 className="mt-1 text-4xl font-bold tracking-tight">Hello, {firstName}<span className="text-brand-primary">.</span></h2>
          <p className="mt-1 text-sm text-brand-muted">Here’s what needs your attention today.</p>
        </div>
        <Link to="/enquiry" className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 hover:bg-brand-primary-hover">
          <Plus className="size-4" aria-hidden />New enquiry
        </Link>
      </div>

      {overview.error && <ErrorNote message={overview.error.message} onRetry={overview.reload} />}

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat label="Active leads" value={o?.active_leads} icon={Users} to="/leads?status=LEAD" note="In the pipeline" />
        <Stat label="Follow-ups due" value={o ? o.followups.today + o.followups.overdue : undefined} icon={Bell} to="/leads?followup=today" note={o ? `${o.followups.overdue} overdue` : ""} />
        <Stat label="Upcoming demos" value={o?.upcoming_demos} icon={Video} to="/demos" note="Scheduled from today" />
        <Stat dark label="Joined this month" value={o?.joined_this_month} icon={ArrowUpRight} to="/joined" note="Registered students" />
      </div>

      <div className="grid gap-5 lg:grid-cols-[1.6fr_1fr]">
        <Card className="p-5">
          <div className="mb-4 flex items-start justify-between gap-3">
            <div>
              <h3 className="text-lg font-semibold">Follow-ups</h3>
              <p className="text-sm text-brand-muted">{o ? `${o.followups.today} due today · ${o.followups.overdue} overdue · ${o.due_reminders} ${o.due_reminders === 1 ? "reminder" : "reminders"}` : " "}</p>
            </div>
            <div className="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-brand-primary"><Bell className="size-5" aria-hidden /></div>
          </div>
          <Segmented label="Follow-up list" value={tab} onChange={setTab} options={[
            { value: "today", label: `Due today${o ? ` (${o.followups.today})` : ""}` },
            { value: "overdue", label: `Overdue${o ? ` (${o.followups.overdue})` : ""}` },
            { value: "reminders", label: `Reminders${o ? ` (${o.due_reminders})` : ""}` },
          ]} />
          <div className="mt-3">
            {tab !== "reminders" ? (
              followups.loading && !followups.data ? <Spinner /> : followups.error ? <ErrorNote message={followups.error.message} onRetry={followups.reload} /> :
              followups.data && followups.data.data.length ? (
                <ul className="divide-y divide-brand-border">
                  {followups.data.data.slice(0, 8).map((lead) => (
                    <li key={lead.id}>
                      <Link to={`/leads/${lead.id}`} className="flex items-center gap-3 rounded-xl px-2 py-3 hover:bg-brand-fill">
                        <div className="min-w-0 flex-1">
                          <p className="truncate font-medium">{leadName(lead)}{Boolean(Number(lead.is_hot)) && <Flame className="ml-1 inline size-4 text-orange-500" aria-label="Hot lead" />}</p>
                          <p className="truncate text-xs text-brand-muted">{[lead.grade, lead.subject].filter(Boolean).join(" · ") || lead.phone}</p>
                        </div>
                        <span className={cx("text-xs font-semibold", tab === "overdue" ? "text-brand-danger" : "text-brand-primary")}>{relativeDay(lead.next_follow_up_on, o?.today ?? "")}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : <EmptyState icon={CheckCircle2} title="A clear to-do list">{tab === "today" ? "No follow-ups are due today." : "Nothing is overdue."}</EmptyState>
            ) : reminders.loading && !reminders.data ? <Spinner /> : reminders.error ? <ErrorNote message={reminders.error.message} onRetry={reminders.reload} /> :
              reminders.data && reminders.data.data.length ? (
                <ul className="divide-y divide-brand-border">
                  {reminders.data.data.slice(0, 8).map((reminder) => (
                    <li key={reminder.id}>
                      <Link to={`/leads/${reminder.lead_id}`} className="flex items-center gap-3 rounded-xl px-2 py-3 hover:bg-brand-fill">
                        <div className="min-w-0 flex-1">
                          <p className="truncate font-medium">{reminder.text}</p>
                          <p className="truncate text-xs text-brand-muted">{reminder.lead_name || "Lead"}</p>
                        </div>
                        <span className="text-xs font-semibold text-brand-primary">{relativeDay(reminder.due_on, o?.today ?? "")}{reminder.due_time ? ` · ${formatTime(reminder.due_time)}` : ""}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : <EmptyState icon={CheckCircle2} title="No reminders due">Open a lead and add a reminder for your next conversation.</EmptyState>}
          </div>
          <Link to="/leads" className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-primary">Browse leads <ArrowRight className="size-4" /></Link>
        </Card>

        <div className="space-y-5">
          <Card className="p-5">
            <div className="mb-3 flex items-start justify-between">
              <div><h3 className="text-lg font-semibold">Upcoming demos</h3><p className="text-sm text-brand-muted">Your next conversations</p></div>
              <div className="flex size-10 items-center justify-center rounded-xl bg-slate-100 text-brand-soft"><CalendarDays className="size-5" aria-hidden /></div>
            </div>
            {demos.loading && !demos.data ? <Spinner /> : demos.data && demos.data.data.length ? (
              <ul className="space-y-2">
                {demos.data.data.slice(0, 4).map((demo) => (
                  <li key={demo.id}>
                    <Link to={`/leads/${demo.lead_id}`} className="flex items-center gap-3 rounded-xl border border-brand-border px-3 py-2.5 hover:bg-brand-fill">
                      <div className="w-14 shrink-0 text-center"><p className="text-xs font-semibold text-brand-primary">{relativeDay(demo.scheduled_on, o?.today ?? "")}</p><p className="text-xs text-brand-muted">{formatTime(demo.scheduled_time)}</p></div>
                      <div className="min-w-0"><p className="truncate text-sm font-medium">{leadName({ name: demo.lead_name ?? "" })}</p><p className="truncate text-xs text-brand-muted">{[demo.subject_name, demo.teacher_name].filter(Boolean).join(" · ") || "Teacher to be confirmed"}</p></div>
                    </Link>
                  </li>
                ))}
              </ul>
            ) : <p className="flex items-center gap-2 py-4 text-sm text-brand-muted"><Video className="size-4" /> No demos scheduled.</p>}
            <Link to="/demos" className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-brand-primary">View demo schedule <ArrowRight className="size-4" /></Link>
          </Card>

          <Card className="border-t-4 border-t-brand-primary p-5">
            <h3 className="text-lg font-semibold">Pipeline</h3>
            <p className="mb-3 text-sm text-brand-muted">Where every enquiry stands.</p>
            <ul className="space-y-1">
              {(Object.keys(STAGES) as (keyof typeof STAGES)[]).map((stage) => (
                <li key={stage}>
                  <Link to={`/leads?stage=${stage}`} className="flex items-center justify-between rounded-lg px-2 py-2 text-sm hover:bg-brand-fill">
                    <span>{STAGES[stage].label}</span><span className="font-semibold">{o ? o.stages[stage] : "–"}</span>
                  </Link>
                </li>
              ))}
            </ul>
            {o && o.pending_transfers > 0 && <Link to="/transfers" className="mt-3 flex items-center justify-between rounded-xl bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">Pending transfers <span className="font-semibold">{o.pending_transfers}</span></Link>}
          </Card>
        </div>
      </div>
    </div>
  );
}

function Stat({ label, value, icon: Icon, to, note, dark }: { label: string; value: number | undefined; icon: typeof Users; to: string; note: string; dark?: boolean }) {
  return (
    <Link to={to} className={cx("rounded-2xl border p-4 transition hover:-translate-y-0.5 hover:shadow-md sm:p-5", dark ? "border-brand-ink bg-brand-ink text-white" : "border-brand-border bg-white")}>
      <div className="flex items-start justify-between"><p className={cx("text-sm font-medium", dark ? "text-white" : "text-brand-soft")}>{label}</p><Icon className={cx("size-5", dark ? "text-white" : "text-brand-muted")} aria-hidden /></div>
      <p className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{value ?? "–"}</p>
      <p className={cx("mt-1 text-xs", dark ? "text-slate-300" : "text-brand-muted")}>{note}</p>
    </Link>
  );
}
