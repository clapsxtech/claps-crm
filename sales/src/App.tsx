import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";
import { ShieldAlert } from "lucide-react";
import { SessionProvider, useSession } from "./lib/session";
import { Layout } from "./components/Layout";
import { Button, Spinner, ToastProvider } from "./components/ui";
import { Login } from "./pages/Login";
import { Home } from "./pages/Home";
import { Leads } from "./pages/Leads";
import { LeadDetail } from "./pages/LeadDetail";
import { NewEnquiry } from "./pages/NewEnquiry";
import { Demos } from "./pages/Demos";
import { Teachers } from "./pages/Teachers";
import { Transfers } from "./pages/Transfers";

function Gate() {
  const { user, unit, loading, signOut } = useSession();
  if (loading) return <div className="flex min-h-dvh items-center justify-center"><Spinner label="Opening ClapsCRM" /></div>;
  if (!user) return <Login />;
  if (!unit) {
    return (
      <div className="flex min-h-dvh items-center justify-center px-4">
        <div className="card max-w-sm p-6 text-center">
          <ShieldAlert className="mx-auto mb-3 size-8 text-brand-muted" />
          <p className="font-semibold">No Sales CRM access</p>
          <p className="mt-1 text-sm text-brand-muted">Ask a team manager to give your ERP account a sales role.</p>
          <Button className="mt-4" onClick={() => void signOut()}>Sign out</Button>
        </div>
      </div>
    );
  }
  return (
    <Routes key={unit.id}>
      <Route element={<Layout />}>
        <Route index element={<Home />} />
        <Route path="leads" element={<Leads />} />
        <Route path="leads/:id" element={<LeadDetail />} />
        <Route path="joined" element={<Leads joinedOnly />} />
        <Route path="enquiry" element={<NewEnquiry />} />
        <Route path="demos" element={<Demos />} />
        <Route path="teachers" element={<Teachers />} />
        <Route path="transfers" element={<Transfers />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}

export function App() {
  return (
    <BrowserRouter>
      <ToastProvider>
        <SessionProvider>
          <Gate />
        </SessionProvider>
      </ToastProvider>
    </BrowserRouter>
  );
}
