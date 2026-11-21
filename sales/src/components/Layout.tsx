import { useState, type ReactNode } from "react";
import { NavLink, Outlet, useLocation } from "react-router-dom";
import { ArrowLeftRight, GraduationCap, Home, LogOut, MoreHorizontal, Users, Video, X, Zap, type LucideIcon } from "lucide-react";
import { useSession } from "../lib/session";
import { initials, roleLabel } from "../lib/format";
import { cx } from "./ui";

const NAV: { to: string; label: string; icon: LucideIcon; end?: boolean }[] = [
  { to: "/", label: "Home", icon: Home, end: true },
  { to: "/leads", label: "Leads", icon: Users },
  { to: "/demos", label: "Demos", icon: Video },
  { to: "/joined", label: "Joined", icon: GraduationCap },
  { to: "/teachers", label: "Teachers", icon: GraduationCap },
  { to: "/transfers", label: "Transfers", icon: ArrowLeftRight },
];
const MOBILE_MAIN = ["/", "/leads", "/demos", "/joined"];

function Brand() {
  return (
    <div className="flex items-center gap-3">
      <div className="flex size-9 items-center justify-center rounded-xl bg-brand-ink text-white"><Zap className="size-5" aria-hidden /></div>
      <span className="text-[17px] font-semibold tracking-tight">ClapsCRM</span>
    </div>
  );
}

function UnitSwitcher() {
  const { user, unit, chooseUnit } = useSession();
  if (!user || user.units.length < 2 || !unit) return null;
  return (
    <label className="block px-3 pb-3">
      <span className="field-label mb-1">Team</span>
      <select value={unit.id} onChange={(event) => chooseUnit(Number(event.target.value))} className="control py-2">
        {user.units.map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name}</option>)}
      </select>
    </label>
  );
}

function UserCard() {
  const { user, unit, signOut } = useSession();
  if (!user) return null;
  return (
    <div className="flex items-center gap-3 border-t border-brand-border p-4">
      <div className="flex size-10 items-center justify-center rounded-full border border-brand-border bg-brand-fill text-sm font-semibold">{initials(user.name)}</div>
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold">{user.name}</p>
        <p className="truncate text-xs text-brand-muted">{roleLabel(unit?.role ?? null)}</p>
      </div>
      <button type="button" onClick={() => void signOut()} title="Sign out" aria-label="Sign out" className="rounded-lg p-2 text-brand-muted hover:bg-slate-100 hover:text-brand-ink"><LogOut className="size-4" /></button>
    </div>
  );
}

const TITLES: Record<string, string> = { "/": "Overview", "/leads": "Leads", "/demos": "Demos", "/joined": "Joined", "/teachers": "Teachers", "/transfers": "Transfers", "/enquiry": "New enquiry" };

export function Layout() {
  const location = useLocation();
  const [moreOpen, setMoreOpen] = useState(false);
  const root = "/" + (location.pathname.split("/")[1] ?? "");
  const title = location.pathname.startsWith("/leads/") ? "Lead" : TITLES[root] ?? "ClapsCRM";
  const { user } = useSession();

  return (
    <div className="min-h-dvh lg:pl-64">
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">Skip to main content</a>
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-brand-border bg-white lg:flex">
        <div className="border-b border-brand-border px-5 py-5"><Brand /></div>
        <nav aria-label="Main" className="flex-1 space-y-1 overflow-y-auto px-3 py-3">
          {NAV.map((item) => <SideLink key={item.to} {...item} />)}
        </nav>
        <UnitSwitcher />
        <UserCard />
      </aside>

      <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-brand-border bg-white/90 px-4 backdrop-blur sm:px-6">
        <div className="flex items-center gap-3">
          <div className="lg:hidden"><div className="flex size-8 items-center justify-center rounded-lg bg-brand-ink text-white"><Zap className="size-4" aria-hidden /></div></div>
          <h1 className="text-lg font-semibold tracking-tight">{title}</h1>
        </div>
        {user && <div className="flex size-9 items-center justify-center rounded-full border border-brand-border bg-brand-fill text-xs font-semibold lg:hidden" aria-hidden>{initials(user.name)}</div>}
      </header>

      <main id="main" className="mx-auto max-w-6xl px-4 pb-28 pt-5 sm:px-6 lg:pb-12">
        <Outlet />
      </main>

      <nav aria-label="Main" className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 gap-1 border-t border-brand-border bg-white px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 lg:hidden">
        {NAV.filter((item) => MOBILE_MAIN.includes(item.to)).map((item) => <TabLink key={item.to} {...item} />)}
        <button type="button" onClick={() => setMoreOpen(true)} className="flex flex-col items-center gap-1 rounded-2xl py-2 text-[11px] font-medium text-brand-muted">
          <MoreHorizontal className="size-5" aria-hidden />More
        </button>
      </nav>

      {moreOpen && (
        <div className="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" onClick={() => setMoreOpen(false)}>
          <div className="absolute inset-x-0 bottom-0 rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)]" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-center justify-between px-5 pt-4"><Brand /><button type="button" aria-label="Close" onClick={() => setMoreOpen(false)} className="rounded-lg p-2"><X className="size-5" /></button></div>
            <nav aria-label="More" className="space-y-1 p-3" onClick={() => setMoreOpen(false)}>
              {NAV.filter((item) => !MOBILE_MAIN.includes(item.to)).map((item) => <SideLink key={item.to} {...item} />)}
            </nav>
            <UnitSwitcher />
            <UserCard />
          </div>
        </div>
      )}
    </div>
  );
}

function SideLink({ to, label, icon: Icon, end }: { to: string; label: string; icon: LucideIcon; end?: boolean }) {
  return (
    <NavLink to={to} end={end} className={({ isActive }) => cx("flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition", isActive ? "bg-brand-primary text-white shadow-sm shadow-blue-600/20" : "text-brand-soft hover:bg-slate-100")}>
      <Icon className="size-[18px]" aria-hidden />{label}
    </NavLink>
  );
}

function TabLink({ to, label, icon: Icon, end }: { to: string; label: string; icon: LucideIcon; end?: boolean }) {
  return (
    <NavLink to={to} end={end} className={({ isActive }) => cx("flex flex-col items-center gap-1 rounded-2xl py-2 text-[11px] font-medium", isActive ? "bg-brand-primary text-white" : "text-brand-muted")}>
      <Icon className="size-5" aria-hidden />{label}
    </NavLink>
  );
}

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: ReactNode }) {
  return (
    <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 className="text-2xl font-bold tracking-tight sm:text-[28px]">{title}</h2>
        {subtitle && <p className="mt-1 text-sm text-brand-muted">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  );
}
