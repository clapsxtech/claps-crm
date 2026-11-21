# ClapsCRM — sales.clapslearn.in

The sales team's app. It runs as the Cloudflare Worker `claps-crm` and keeps
**no data of its own**: every lead, follow-up, reminder, demo and transfer is
read from and saved to the ERP (erp.clapslearn.in, repository
`clapsxtech/claps-erp`) through the ERP's sales API, using the same rules and
permissions as the ERP's Sales CRM screen. Staff sign in with their ERP accounts.

The app is in [`sales/`](sales/). See [`sales/README.md`](sales/README.md) for
development and deployment.

The ERP itself is no longer in this repository; it lives in `clapsxtech/claps-erp`.
