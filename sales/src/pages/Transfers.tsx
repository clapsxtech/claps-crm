import { useState } from "react";
import { Link } from "react-router-dom";
import { ArrowLeftRight } from "lucide-react";
import { api, unitPath, type Page, type Transfer } from "../lib/api";
import { useSession, useUnit } from "../lib/session";
import { useApi } from "../lib/useApi";
import { formatDate, leadName } from "../lib/format";
import { PageHeader } from "../components/Layout";
import { Button, Card, EmptyState, ErrorNote, Pager, Spinner, useToast } from "../components/ui";

export function Transfers() {
  const unit = useUnit();
  const { user, can } = useSession();
  const toast = useToast();
  const [page, setPage] = useState(1);
  const transfers = useApi<Page<Transfer>>(unitPath(unit.id, `/transfers?page=${page}`));
  const [busy, setBusy] = useState<number | null>(null);

  async function resolve(transfer: Transfer, status: "accepted" | "rejected" | "cancelled") {
    setBusy(transfer.id);
    try {
      await api(unitPath(unit.id, "/actions/resolve-transfer"), { body: { transfer_id: transfer.id, status } });
      toast("success", status === "accepted" ? "Transfer accepted." : status === "rejected" ? "Transfer declined." : "Transfer cancelled.");
      transfers.reload();
    } catch (error) {
      toast("error", (error as Error).message);
    } finally {
      setBusy(null);
    }
  }

  return (
    <div>
      <PageHeader title="Lead transfers" subtitle="Leads waiting to move between team members." />
      {transfers.error ? <ErrorNote message={transfers.error.message} onRetry={transfers.reload} /> :
        transfers.loading && !transfers.data ? <Card><Spinner /></Card> :
        transfers.data && transfers.data.data.length === 0 ? <Card><EmptyState icon={ArrowLeftRight} title="No pending transfers">Transfers you send or receive appear here until they are resolved.</EmptyState></Card> :
        transfers.data && (
          <>
            <ul className="space-y-3">
              {transfers.data.data.map((transfer) => (
                <li key={transfer.id} className="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                  <div className="min-w-0 flex-1">
                    <Link to={`/leads/${transfer.lead_id}`} className="font-semibold hover:text-brand-primary">{leadName({ name: transfer.lead_name })}</Link>
                    <p className="text-sm text-brand-muted">{transfer.sender_name ?? "Someone"} → {transfer.recipient_name ?? "Someone"} · {formatDate(transfer.created_at, true)}</p>
                  </div>
                  {can("resolve-transfer") && (transfer.to_user_id === user?.id ? (
                    <div className="flex gap-2"><Button size="sm" variant="primary" busy={busy === transfer.id} onClick={() => void resolve(transfer, "accepted")}>Accept</Button><Button size="sm" disabled={busy === transfer.id} onClick={() => void resolve(transfer, "rejected")}>Decline</Button></div>
                  ) : <Button size="sm" busy={busy === transfer.id} onClick={() => void resolve(transfer, "cancelled")}>Cancel</Button>)}
                </li>
              ))}
            </ul>
            <Pager page={transfers.data.page} lastPage={transfers.data.last_page} total={transfers.data.total} onPage={setPage} />
          </>
        )}
    </div>
  );
}
