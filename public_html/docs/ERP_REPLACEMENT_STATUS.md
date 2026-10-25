# Unified interface release — 25 October 2026

The main application entry is `/`. `/erp` and `/home` redirect there. The tools
directory is `/tools`; `/legacy` redirects to it for existing bookmarks.

All administration, academic catalogue and historical-report screens now render
inside the shared ERP shell, including topics, subjects, marks, original student
and teacher directories, assignment records, monthly reports, teacher hours,
work-hour records, dues, timetables, users, roles and settings. Edit dialogs,
DataTables filters, exports and existing permission checks remain in place.
The sidebar groups records, reports and administration and opens the current group.

The six teacher routes share a responsive Teacher workspace:
`/timetableReport`, `/attendance`, `/attendanceReport`, `/resultform`, `/markreport`
and `/student-attendance-report`. Attendance has an ordered teacher/student and
lesson-details form. Reports have visible field labels, masked credentials and
shared navigation. Public verification and signed-in report access are unchanged.

This replaces the old presentation, not historical data. Original records and
unit-scoped operational records retain their existing storage and meaning.
No financial balances, roles, production records or integration credentials were
changed. Optional spreadsheet integrations still require provider configuration.

## Verification

375 PHP tests / 3,198 assertions pass normally and with the D1 compatibility adapter.
All 40 JavaScript tests and TypeScript checking pass. The new tests cover all
17 administration page routes, the six teacher routes, guest report redirects and
nonadministrator denial. An isolated local database was used for browser topic
creation and editing. Mobile teacher navigation and form structure were checked.
