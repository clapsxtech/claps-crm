import { useState, type FormEvent } from "react";
import { KeyRound, Lock, Mail, ShieldCheck, Zap } from "lucide-react";
import { api, ApiError, type User } from "../lib/api";
import { useSession } from "../lib/session";
import { Button, ErrorNote, Field, Input } from "../components/ui";

export function Login() {
  const { setUser } = useSession();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [needsCode, setNeedsCode] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const body = await api<{ user?: User; two_factor_required?: boolean }>("/login", { body: { email, password, ...(needsCode ? { code } : {}) } });
      if (body.two_factor_required) { setNeedsCode(true); return; }
      if (body.user) setUser(body.user);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Sign-in failed. Try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex min-h-dvh items-center justify-center bg-brand-bg px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center text-center">
          <div className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand-ink text-white"><Zap className="size-6" aria-hidden /></div>
          <h1 className="text-2xl font-bold tracking-tight">ClapsCRM</h1>
          <p className="mt-1 text-sm text-brand-muted">Sign in with your Claps Learn ERP account.</p>
        </div>
        <form onSubmit={submit} className="card space-y-4 p-6 shadow-sm">
          {error && <ErrorNote message={error} />}
          {!needsCode ? (
            <>
              <Field label="Email" icon={Mail} required>{(id) => <Input id={id} type="email" autoComplete="username" withIcon required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@clapslearn.com" />}</Field>
              <Field label="Password" icon={Lock} required>{(id) => <Input id={id} type="password" autoComplete="current-password" withIcon required value={password} onChange={(e) => setPassword(e.target.value)} />}</Field>
            </>
          ) : (
            <>
              <div className="flex items-start gap-3 rounded-xl bg-blue-50 p-3 text-sm text-blue-900"><ShieldCheck className="mt-0.5 size-4 shrink-0" />Enter the 6-digit code from your authenticator app, or a recovery code.</div>
              <Field label="Verification code" icon={KeyRound} required>{(id) => <Input id={id} inputMode="text" autoComplete="one-time-code" withIcon required autoFocus value={code} onChange={(e) => setCode(e.target.value.trim())} />}</Field>
            </>
          )}
          <Button type="submit" variant="dark" size="lg" className="w-full" busy={busy}>{needsCode ? "Verify and sign in" : "Sign in"}</Button>
          {needsCode && <button type="button" className="w-full text-center text-sm font-medium text-brand-muted hover:text-brand-ink" onClick={() => { setNeedsCode(false); setCode(""); }}>Use a different account</button>}
        </form>
        <p className="mt-6 text-center text-xs text-brand-muted">Forgot your password? Reset it from the ERP at erp.clapslearn.in.</p>
      </div>
    </div>
  );
}
