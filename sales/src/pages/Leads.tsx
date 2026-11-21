import { useEffect, useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { GraduationCap, MessageCircle, Phone, Plus, Search, Star, Users, X } from "lucide-react";
import { api, query, unitPath, type Lead, type Page } from "../lib/api";
import { useSession, useUnit } from "../lib/session";
import { useApi } from "../lib/useApi";
import { formatDate, leadName, relativeDay, STAGES, todayIn, truthy, whatsappLink } from "../lib/format";
import { PageHeader } from "../components/Layout";
import { Button, Card, cx, EmptyState, ErrorNote, Pager, Pill, Select, Spinner, useToast } from "../components/ui";

const STATUS_TABS = [{ value: "", label: "All" }, { value: "LEAD", label: "Active" }, { value: "JOINED", label: "Joined" }, { value: "LOST", label: "Lost" }];

export function Leads({ joinedOnly = false }: { joinedOnly?: boolean }) {
  const unit = useUnit();
  const { can } = useSession();
  const toast = useToast();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const status = joinedOnly ? "JOINED" : params.get("status") ?? "";
  const stage = params.get("stage") ?? "";
  const followup = params.get("followup") ?? "";
  const hot = params.get("hot") === "1";
  const page = Number(params.get("page") ?? 1) || 1;
  const [search, setSearch] = useState(params.get("q") ?? "");
  const q = params.get("q") ?? "";

  useEffect(() => {
    const handle = setTimeout(() => { if (search !== q) update({ q: search || null }); }, 300);
    return () => clearTimeout(handle);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  function update(changes: Record<string, string | null>) {
    const next = new URLSearchParams(params);
    for (const [key, value] of Object.entries(changes)) value ? next.set(key, value) : next.delete(key);
    if (!("page" in changes)) next.delete("page");
    setParams(next, { replace: true });
  }

  const path = unitPath(unit.id, `/leads${query({ q, status, stage, followup, hot, page })}`);
  const leads = useApi<Page<Lead>>(path);
  const today = todayIn(unit.timezone);
  const filtered = Boolean(q || stage || followup || hot || (!joinedOnly && status));

  async function toggleHot(lead: Lead) {
    const next = !truthy(lead.is_hot);
    leads.setData((current) => current && { ...current, data: current.data.map((row) => row.id === lead.id ? { ...row, is_hot: next } : row) });
    try {
      await api(unitPath(unit.id, "/actions/update-lead"), { body: { lead_id: lead.id, is_hot: next } });
    } catch (error) {
      toast("error", (error as Error).message);
      leads.reload();
    }
  }

  return (
    <div>
      <PageHeader
        title={joinedOnly ? "Joined students" : "Lead pipeline"}
        subtitle={joinedOnly ? "Enquiries that registered as students." : "Every enquiry, from first conversation to joining."}
        actions={!joinedOnly && can("create-lead") ? <Button variant="primary" icon={Plus} onClick={() => navigate("/enquiry")}>New enquiry</Button> : undefined}
      />

      <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <label className="relative flex-1">
          <span className="sr-only">Search leads</span>
          <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" aria-hidden />
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search name, phone, email or admission code…" className="control h-12 rounded-2xl pl-11" />
        </label>
        {!joinedOnly && (
          <div className="flex flex-wrap gap-2">
            <button type="button" onClick={() => update({ hot: hot ? null : "1" })} aria-pressed={hot}
              className={cx("inline-flex h-10 items-center gap-2 rounded-xl border px-3 text-sm font-semibold", hot ? "border-orange-300 bg-orange-50 text-orange-700" : "border-brand-border bg-white text-brand-ink")}>
              <Star className={cx("size-4", hot && "fill-orange-400 text-orange-500")} aria-hidden />Hot leads
            </button>
            <Select aria-label="Follow-up" value={followup} onChange={(event) => update({ followup: event.target.value || null })} className="h-10 w-auto py-0">
              <option value="">All follow-ups</option><option value="today">Due today</option><option value="overdue">Overdue</option><option value="unscheduled">Not scheduled</option>
            </Select>
            <Select aria-label="Stage" value={stage} onChange={(event) => update({ stage: event.target.value || null })} className="h-10 w-auto py-0">
              <option value="">All stages</option>
              {Object.entries(STAGES).map(([value, info]) => <option key={value} value={value}>{info.label}</option>)}
            </Select>
          </div>
        )}
      </div>

      {!joinedOnly && (
        <div role="tablist" aria-label="Lead status" className="mb-4 flex gap-1 overflow-x-auto rounded-2xl border border-brand-border bg-white p-1">
          {STATUS_TABS.map((tab) => (
            <button key={tab.value} role="tab" type="button" aria-selected={status === tab.value} onClick={() => update({ status: tab.value || null })}
              className={cx("whitespace-nowrap rounded-xl px-4 py-2 text-sm font-medium", status === tab.value ? "bg-brand-ink text-white" : "text-brand-soft hover:bg-slate-100")}>
              {tab.label}
            </button>
          ))}
        </div>
      )}

      <div className="mb-2 flex items-center justify-between text-sm text-brand-muted">
        <span>{leads.data ? `${leads.data.total} ${leads.data.total === 1 ? "lead" : "leads"}` : " "}</span>
        {filtered && <button type="button" onClick={() => { setSearch(""); setParams(new URLSearchParams(), { replace: true }); }} className="inline-flex items-center gap-1 font-medium text-brand-primary"><X className="size-4" />Clear filters</button>}
      </div>

      {leads.error ? <ErrorNote message={leads.error.message} onRetry={leads.reload} /> :
        leads.loading && !leads.data ? <Card><Spinner label="Loading leads" /></Card> :
        leads.data && leads.data.data.length === 0 ? (
          <Card><EmptyState icon={joinedOnly ? GraduationCap : Users} title={filtered ? "No leads match your filters" : joinedOnly ? "No joined students yet" : "No leads yet"}>
            {filtered ? "Try a different search or clear your filters." : joinedOnly ? "Register a lead from its page once the student joins." : "Add your first enquiry to start the pipeline."}
          </EmptyState></Card>
        ) : leads.data && (
          <>
            <Card className="hidden overflow-hidden md:block">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-brand-border bg-brand-fill text-xs font-semibold uppercase tracking-wider text-brand-muted">
                  <tr><th className="w-10 px-4 py-3"><span className="sr-only">Hot</span></th><th className="px-2 py-3">Lead</th><th className="px-4 py-3">Course</th><th className="px-4 py-3">Stage</th><th className="px-4 py-3">{joinedOnly ? "Joined" : "Next follow-up"}</th><th className="px-4 py-3">Owner</th><th className="px-4 py-3"><span className="sr-only">Contact</span></th></tr>
                </thead>
                <tbody className="divide-y divide-brand-border">
                  {leads.data.data.map((lead) => (
                    <tr key={lead.id} className="cursor-pointer hover:bg-brand-fill" onClick={() => navigate(`/leads/${lead.id}`)}>
                      <td className="px-4 py-3" onClick={(event) => event.stopPropagation()}><HotStar lead={lead} disabled={!can("update-lead")} onToggle={toggleHot} /></td>
                      <td className="px-2 py-3"><Link to={`/leads/${lead.id}`} className="font-semibold hover:text-brand-primary" onClick={(event) => event.stopPropagation()}>{leadName(lead)}</Link><p className="text-xs text-brand-muted">{lead.phone}</p></td>
                      <td className="px-4 py-3"><p className="font-medium">{lead.grade || "—"}</p><p className="text-xs text-brand-muted">{[lead.subject, lead.syllabus].filter(Boolean).join(" · ") || "No subject"}</p></td>
                      <td className="px-4 py-3"><Pill className={STAGES[lead.crm_stage]?.className ?? STAGES.new.className}>{STAGES[lead.crm_stage]?.label ?? lead.crm_stage}</Pill></td>
                      <td className="px-4 py-3 text-sm">{joinedOnly ? formatDate(lead.joined_on, true) : <FollowUp lead={lead} today={today} />}</td>
                      <td className="px-4 py-3 text-sm">{lead.assigned_name || "Unassigned"}<p className="text-xs text-brand-muted">{formatDate(lead.enquiry_date, true)}{lead.source ? ` · ${lead.source}` : ""}</p></td>
                      <td className="px-4 py-3" onClick={(event) => event.stopPropagation()}><ContactButtons lead={lead} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </Card>

            <ul className="space-y-3 md:hidden">
              {leads.data.data.map((lead) => (
                <li key={lead.id} className="card p-4">
                  <div className="flex items-start gap-3">
                    <HotStar lead={lead} disabled={!can("update-lead")} onToggle={toggleHot} />
                    <Link to={`/leads/${lead.id}`} className="min-w-0 flex-1">
                      <p className="truncate font-semibold">{leadName(lead)}</p>
                      <p className="truncate text-xs text-brand-muted">{[lead.grade, lead.subject].filter(Boolean).join(" · ") || lead.phone}</p>
                    </Link>
                    <Pill className={STAGES[lead.crm_stage]?.className ?? STAGES.new.className}>{STAGES[lead.crm_stage]?.label ?? lead.crm_stage}</Pill>
                  </div>
                  <div className="mt-3 flex items-center justify-between gap-2 border-t border-brand-border pt-3 text-sm">
                    <span>{joinedOnly ? `Joined ${formatDate(lead.joined_on, true)}` : <FollowUp lead={lead} today={today} />}</span>
                    <ContactButtons lead={lead} />
                  </div>
                </li>
              ))}
            </ul>
            <Pager page={leads.data.page} lastPage={leads.data.last_page} total={leads.data.total} onPage={(value) => update({ page: String(value) })} />
          </>
        )}
    </div>
  );
}

function HotStar({ lead, disabled, onToggle }: { lead: Lead; disabled: boolean; onToggle: (lead: Lead) => void }) {
  const hot = truthy(lead.is_hot);
  return (
    <button type="button" disabled={disabled} onClick={() => onToggle(lead)} aria-pressed={hot} aria-label={hot ? `Unmark ${leadName(lead)} as hot` : `Mark ${leadName(lead)} as hot`}
      className="flex size-9 shrink-0 items-center justify-center rounded-full border border-brand-border bg-white disabled:opacity-50">
      <Star className={cx("size-4", hot ? "fill-orange-400 text-orange-500" : "text-slate-400")} aria-hidden />
    </button>
  );
}

function FollowUp({ lead, today }: { lead: Lead; today: string }) {
  if (lead.status !== "LEAD") return <span className="text-brand-muted">—</span>;
  const overdue = lead.next_follow_up_on !== null && lead.next_follow_up_on < today;
  return <span className={cx("font-medium", overdue ? "text-brand-danger" : lead.next_follow_up_on === today ? "text-brand-primary" : "text-brand-soft")}>{relativeDay(lead.next_follow_up_on, today)}</span>;
}

export function ContactButtons({ lead }: { lead: Pick<Lead, "phone" | "country"> }) {
  const wa = whatsappLink(lead.phone, lead.country);
  return (
    <div className="flex gap-1.5">
      <a href={`tel:${lead.phone.replace(/[^0-9+]/g, "")}`} aria-label="Call" title="Call" className="flex size-9 items-center justify-center rounded-xl border border-brand-border bg-white text-brand-soft hover:bg-brand-fill"><Phone className="size-4" /></a>
      {wa && <a href={wa} target="_blank" rel="noreferrer" aria-label="WhatsApp" title="WhatsApp" className="flex size-9 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"><MessageCircle className="size-4" /></a>}
    </div>
  );
}
