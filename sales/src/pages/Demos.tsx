import { useState } from "react";
import { Link } from "react-router-dom";
import { Link2, Video } from "lucide-react";
import { unitPath, type Demo, type Page } from "../lib/api";
import { useUnit } from "../lib/session";
import { useApi } from "../lib/useApi";
import { DEMO_STATUS, formatTime, leadName, relativeDay, todayIn } from "../lib/format";
import { PageHeader } from "../components/Layout";
import { DemoContacts } from "../components/DemoContacts";
import { Card, EmptyState, ErrorNote, Pager, Pill, Segmented, Spinner } from "../components/ui";

export function Demos() {
  const unit = useUnit();
  const [scope, setScope] = useState<"upcoming" | "all">("upcoming");
  const [page, setPage] = useState(1);
  const demos = useApi<Page<Demo>>(unitPath(unit.id, `/demos?scope=${scope}&page=${page}`));
  const today = todayIn(unit.timezone);

  return (
    <div>
      <PageHeader title="Demo schedule" subtitle="Every scheduled demo should lead to an admission." />
      <div className="mb-4"><Segmented label="Demo scope" value={scope} onChange={(value) => { setScope(value); setPage(1); }} options={[{ value: "upcoming", label: "Upcoming" }, { value: "all", label: "All demos" }]} /></div>
      {demos.error ? <ErrorNote message={demos.error.message} onRetry={demos.reload} /> :
        demos.loading && !demos.data ? <Card><Spinner label="Loading demos" /></Card> :
        demos.data && demos.data.data.length === 0 ? <Card><EmptyState icon={Video} title={scope === "upcoming" ? "No upcoming demos" : "No demos yet"}>Schedule a demo from a lead’s page.</EmptyState></Card> :
        demos.data && (
          <>
            <ul className="grid gap-3 md:grid-cols-2">
              {demos.data.data.map((demo) => {
                const info = DEMO_STATUS[demo.status] ?? DEMO_STATUS.SCHEDULED;
                return (
                  <li key={demo.id} className="card flex flex-col p-4">
                    <div className="flex items-start gap-3">
                      <div className="w-20 shrink-0 rounded-xl bg-blue-50 px-2 py-2 text-center"><p className="text-xs font-semibold text-brand-primary">{relativeDay(demo.scheduled_on, today)}</p><p className="text-sm font-bold">{formatTime(demo.scheduled_time)}</p></div>
                      <div className="min-w-0 flex-1">
                        <Link to={`/leads/${demo.lead_id}`} className="block truncate font-semibold hover:text-brand-primary">{leadName({ name: demo.lead_name ?? "" })}</Link>
                        <p className="truncate text-xs text-brand-muted">{[demo.lead_grade, demo.subject_name].filter(Boolean).join(" · ") || "Course not recorded"}</p>
                        <p className="mt-1.5 truncate text-base font-semibold">{demo.teacher_name ?? <span className="text-sm font-medium text-brand-muted">Teacher to be confirmed</span>}</p>
                      </div>
                      <Pill className={info.className}>{info.label}</Pill>
                    </div>
                    <div className="mt-3 border-t border-brand-border pt-3">
                      <DemoContacts demo={demo} unit={unit} parentPhone={demo.phone} parentCountry={demo.country} studentName={leadName({ name: demo.lead_name ?? "" })} />
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2 text-xs font-semibold">
                      {demo.meet_link && <a href={demo.meet_link} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1.5 text-brand-primary"><Link2 className="size-3.5" />Join link</a>}
                      <Link to={`/leads/${demo.lead_id}`} className="ml-auto rounded-lg px-2.5 py-1.5 text-brand-primary">Open lead</Link>
                    </div>
                  </li>
                );
              })}
            </ul>
            <Pager page={demos.data.page} lastPage={demos.data.last_page} total={demos.data.total} onPage={setPage} />
          </>
        )}
    </div>
  );
}
