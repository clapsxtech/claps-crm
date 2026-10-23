# Isolated cloud browser acceptance

The application runs locally on the cloud computer using the existing PHP runtime, a separate synthetic SQLite database and Laravel's standard development router. The supported cloud desktop Terminal and browser share loopback reachability. No public tunnel, sandbox bypass, production hosting, Sheet write or real financial transfer is involved.

The previously blocked native-exec-to-browser loopback route was not retried as a workaround. Starting the authorized local development server through the documented cloud desktop Terminal resolved the execution-environment separation. A PHP child-process extension configuration issue was avoided by launching the existing configured PHP wrapper directly with the standard router.

Verified through actual browser interaction on the isolated synthetic application:
- Teacher login, own finished lesson,60-minute attendance submission and removal of the submit action.
- Admin import with student charge while teacher accrual remains pending.
- Separate admin approval producing teacher accrual.

- Teacher reload/back preserves the submitted record; access to administrator pages is denied.
- Repeating admin import and approval creates no additional charge or accrual.
- Received-payment recording and ordinary receipt printing show correct amount/reference and no tax claim.
- Payment request creation and receipt allocation fulfill the request without another financial charge.
- Bookkeeping reversal preserves the original receipt, invalidates its acknowledgement and reopens the request. No bank refund is implied.
- Synthetic imported student opening and archived history are visible; historical attendance has no financial effect and no teacher accrual.
- Assigned teacher completes checklist items and then the task through the own-task portal; the administrator dashboard reflects completion.
- Native sidebar logout returns to login and prevents access to authenticated pages.

An ordinary desktop window was narrowed to a 500px viewport: receipt/request forms wrapped without document-level horizontal overflow. Device emulation is blocked by browser organization policy and was not bypassed. This is responsive narrow-desktop evidence, not mobile-device acceptance.

Two issues found and resolved: the receipt timestamp incorrectly said UTC despite installation-time storage, now explicitly labeled; the legacy sidebar logout depended on jQuery, now a native CSRF-protected POST button. Missing legacy vendor assets in the isolated checkout were restored from exact existing repository blobs; fresh dashboard reload then showed no application jQuery/toastr error. No vendor version or published asset content changed.

Source-derived CSV samples remain separate from fabricated browser fixtures; incomplete source dates/cutoff/opening semantics are never invented to make an import pass. Six source-derived linked cohorts were structurally checked (three AA, three BB, six unique events totaling 360 minutes), not financially imported. The physical-column adapter ran against the original twelve selected CSVs; a separate fabricated cohort alone was imported for browser tests.

These checks do not replace acceptance on the intended hosting/database configuration or approved migration reconciliation. Production deployment and real-data cutover were not performed.
