# ClapsCRM sales app

React + Tailwind app served by the Cloudflare Worker `claps-crm` at
**sales.clapslearn.in**. It keeps no data: the Worker forwards `/api/*` to the
ERP's sales API through a service binding to the ERP Worker's `SalesGateway`
entrypoint (`clapslearn-logs`, repository `clapsxtech/claps-erp`). Leads,
follow-ups, reminders, demos and transfers are saved in the ERP database with
the ERP's rules and permissions, so the ERP's Sales CRM screen shows the same
records.

## Sign-in

Staff sign in with their ERP email and password (and two-factor code if they use
one). Accounts need a Sales CRM role on a team in the ERP. The ERP returns a
12-hour token; the Worker keeps it in an `HttpOnly`, `Secure`, `SameSite=Strict`
cookie scoped to `/api`, so page scripts never see it. Writes must send the
`x-claps-sales: 1` header.

## Develop

```sh
npm ci
npm run check     # TypeScript + Worker tests
npm run build     # outputs dist/
```

`npm run dev` serves the UI only; API calls need the Worker and the ERP service
binding, so test against a preview version (below).

## Deploy

Requires the ERP's `SalesGateway` entrypoint to be deployed first.

- Preview without changing sales.clapslearn.in: `npm run cf:preview`
  (uploads a version and prints its preview URL).
- Production: `npm run cf:deploy`.

### Workers Builds settings for the `claps-crm` Worker

| Setting | Value |
| --- | --- |
| Production branch | `main` |
| Root directory | `sales` |
| Build command | `npm run check` |
| Deploy command | `npm run cf:deploy` |
| Non-production branch builds | Disabled |

No secrets are needed: the Worker reaches the ERP only through the service binding.
