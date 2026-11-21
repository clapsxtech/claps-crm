import { useEffect, useState } from "react";
import { GraduationCap, MessageCircle, Search } from "lucide-react";
import { query, unitPath, type TeacherPage } from "../lib/api";
import { useUnit } from "../lib/session";
import { useApi } from "../lib/useApi";
import { whatsappLink } from "../lib/format";
import { PageHeader } from "../components/Layout";
import { Card, EmptyState, ErrorNote, Pager, Pill, Select, Spinner } from "../components/ui";

export function Teachers() {
  const unit = useUnit();
  const [search, setSearch] = useState("");
  const [debounced, setDebounced] = useState("");
  const [subject, setSubject] = useState("");
  const [level, setLevel] = useState("");
  const [page, setPage] = useState(1);
  useEffect(() => { const t = setTimeout(() => { setDebounced(search); setPage(1); }, 300); return () => clearTimeout(t); }, [search]);
  const teachers = useApi<TeacherPage>(unitPath(unit.id, `/teachers${query({ teacher_q: debounced, subject_id: subject, level, teachers_page: page })}`));

  return (
    <div>
      <PageHeader title="Teacher directory" subtitle="Find a teacher for a demo by subject, level and current load." />
      <div className="mb-4 flex flex-col gap-3 md:flex-row">
        <label className="relative flex-1"><span className="sr-only">Search teachers</span><Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by name or CT code…" className="control h-12 rounded-2xl pl-11" /></label>
        <Select aria-label="Subject" value={subject} onChange={(e) => { setSubject(e.target.value); setPage(1); }} className="h-12 md:w-56">
          <option value="">All subjects</option>{teachers.data?.subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </Select>
        <Select aria-label="Level" value={level} onChange={(e) => { setLevel(e.target.value); setPage(1); }} className="h-12 md:w-56">
          <option value="">All levels</option>{Object.entries(teachers.data?.levels ?? {}).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </Select>
      </div>
      {teachers.error ? <ErrorNote message={teachers.error.message} onRetry={teachers.reload} /> :
        teachers.loading && !teachers.data ? <Card><Spinner label="Loading teachers" /></Card> :
        teachers.data && teachers.data.data.length === 0 ? <Card><EmptyState icon={GraduationCap} title="No teachers match">Try another name, subject or level.</EmptyState></Card> :
        teachers.data && (
          <>
            <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {teachers.data.data.map((teacher) => {
                const wa = whatsappLink(teacher.phone ?? null);
                const subjects = teacher.eligibilities?.length ? [...new Set(teacher.eligibilities.map((e) => e.name))].join(", ") : (teacher.candidate_subjects as string | undefined) ?? "";
                return (
                  <li key={teacher.id} className="card flex flex-col p-4">
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0"><p className="truncate font-semibold">{teacher.name}</p><p className="text-xs text-brand-muted">{teacher.code}</p></div>
                      {teacher.category && <Pill className={teacher.category === "Advanced" ? "bg-violet-50 text-violet-700 ring-violet-200" : "bg-slate-100 text-slate-700 ring-slate-200"}>{teacher.category}</Pill>}
                    </div>
                    <p className="mt-2 line-clamp-2 text-sm text-brand-soft">{subjects || "Subjects not recorded"}</p>
                    <div className="mt-3 flex items-center justify-between border-t border-brand-border pt-3 text-xs text-brand-muted">
                      <span>{teacher.levels ? `Levels: ${teacher.levels}` : "Levels not set"}{teacher.tt_hours ? ` · ${teacher.tt_hours} h/week` : ""}</span>
                      {wa && <a href={wa} target="_blank" rel="noreferrer" aria-label={`WhatsApp ${teacher.name}`} className="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 font-semibold text-emerald-700"><MessageCircle className="size-3.5" />WhatsApp</a>}
                    </div>
                  </li>
                );
              })}
            </ul>
            {teachers.data.page && <Pager page={teachers.data.page} lastPage={teachers.data.last_page ?? 1} total={teachers.data.total ?? 0} onPage={setPage} />}
          </>
        )}
    </div>
  );
}
