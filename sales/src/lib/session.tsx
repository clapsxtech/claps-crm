import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { api, setSignedOutHandler, type Unit, type User } from "./api";

interface SessionValue {
  user: User | null;
  unit: Unit | null;
  loading: boolean;
  setUser: (user: User | null) => void;
  chooseUnit: (id: number) => void;
  can: (action: string) => boolean;
  signOut: () => Promise<void>;
}

const SessionContext = createContext<SessionValue | null>(null);
const UNIT_KEY = "claps-sales-unit";

function storedUnit(): number | null {
  try { const value = Number(localStorage.getItem(UNIT_KEY)); return Number.isInteger(value) && value > 0 ? value : null; } catch { return null; }
}

export function SessionProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [unitId, setUnitId] = useState<number | null>(storedUnit());
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setSignedOutHandler(() => setUser(null));
    api<{ user: User }>("/me").then((body) => setUser(body.user)).catch(() => setUser(null)).finally(() => setLoading(false));
  }, []);

  const unit = useMemo(() => {
    if (!user || user.units.length === 0) return null;
    return user.units.find((candidate) => candidate.id === unitId) ?? user.units[0]!;
  }, [user, unitId]);

  const chooseUnit = useCallback((id: number) => {
    setUnitId(id);
    try { localStorage.setItem(UNIT_KEY, String(id)); } catch { /* storage unavailable */ }
  }, []);

  const signOut = useCallback(async () => {
    try { await api("/logout", { method: "POST" }); } finally { setUser(null); }
  }, []);

  const can = useCallback((action: string) => unit?.actions.includes(action) ?? false, [unit]);

  return <SessionContext.Provider value={{ user, unit, loading, setUser, chooseUnit, can, signOut }}>{children}</SessionContext.Provider>;
}

export function useSession(): SessionValue {
  const value = useContext(SessionContext);
  if (!value) throw new Error("useSession must be used inside SessionProvider");
  return value;
}

/** The current team; screens behind the sign-in gate can rely on it. */
export function useUnit(): Unit {
  const { unit } = useSession();
  if (!unit) throw new Error("No team selected");
  return unit;
}
