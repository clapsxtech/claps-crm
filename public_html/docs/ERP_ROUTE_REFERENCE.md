# ClapsLearn ERP — complete route reference

Verified against the local Laravel route collection on **26 October 2026**: **164 route definitions**, including 163 application definitions/redirects and the Sanctum CSRF endpoint. A definition may accept several HTTP methods. Dynamic page and action values are expanded below; they are not separate route definitions.

Read the [user guide and role journeys](ERP_USER_GUIDE.md) first. Download the same registered inventory as [CSV](erp-routes.csv) or [JSON](erp-routes.json). The configured host is `https://erp.clapslearn.in`; deployment of this exact code has not been verified.

## How to read this reference

- `{unit}` is a numeric ERP business-unit ID. Other placeholders refer to records. Use UI-generated links rather than guessing IDs.
- `GET` generally opens a page or reads a report. `HEAD` is the corresponding response without the body. The legacy deletion-confirmation route is explicitly identified below.
- `POST`, `PUT`, `PATCH` and `DELETE` are form/action endpoints, not pages to enter in the browser address bar. Use the ERP forms, authenticated session and CSRF protection. This inventory is not a public API contract.
- Declared middleware is **not a complete permission description**: controller middleware, `TeamAuthorization`, service checks, organization/team membership, role, record ownership, archive state and Sheets authority also apply.
- `auth`/`auth:web` means signed in; `admin` means site administrator, not simply an ordinary team manager. `TeamAuthorization` is abbreviated from its full class name.
- In Sheets-authoritative mode, operational writes are blocked even when the normal role allows them. Read the [transition guide](sheets-transition.md). Login, allowed account/team administration and supported reads remain available.
- Password-recovery endpoints being registered does not prove email delivery is configured. Public registration has no route.

## Team pages: expand `{page?}`

Browser path: `/erp/units/{unit}/business/{page}`. Omitting the page selects `dashboard`, which is not permitted for every role; use the role's landing page instead.

Site administrators can access every page. The table lists additional team roles. `Manager` means `team_manager`; `sales` means `sales_followups`.

| Page value | Screen | Additional team roles |
|---|---|---|
| `dashboard` | Overview | Manager, read-only |
| `admissions` | Admissions & CRM | Manager, sales, read-only |
| `teacher-recruitment` | Teacher recruitment | Manager, academics, sales, read-only; nonmanagers see registered/approved directory |
| `allocation` | Teacher matching | Manager, academics, sales, read-only; mutation rights differ |
| `people` | Students & teachers | Manager, academics, read-only |
| `academics` | Academics | Manager, academics, read-only |
| `timetable` | Timetable | Manager, academics, read-only |
| `attendance` | Attendance | Manager, academics, read-only |
| `money` | Money & receipts | Manager, finance, read-only |
| `followups` | Follow-ups | Manager, sales, read-only |
| `staff` | Team access | Site administrator only |
| `migration` | Migration & exports | Manager, read-only |

The composite `/erp/units/{unit}` workspace and full student profile require manager/read-only detail access or site administration. Private `/tuition` screens require site administrator or team manager; read-only is excluded. Teacher routes require an active linked teacher identity and matching teacher membership.

## Operational action values

The following are **POST** endpoints. Ordinary roles listed are subject to additional domain checks. Site administrators can perform supported actions. Managers have the academic, finance and sales action sets; read-only users have no mutation rights.

### Workspace actions

Path: `/erp/units/{unit}/{action}`.

| Action values | Roles besides site administrator | Purpose |
|---|---|---|
| `student`, `teacher`, `eligibility` | Manager, academics | Create people and teaching eligibility |
| `mark` | Manager, academics | Record an assessment result |
| `assign`, `end-assignment` | Manager, academics | Start/end an enrollment |
| `availability`, `propose`, `confirm`, `materialize` | Manager, academics | Capture availability, agree a recurring schedule, generate dated lessons |
| `reschedule`, `cancel`, `extra`, `exception`, `end-agreement` | Manager, academics | Manage scheduling changes |
| `attendance`, `approve`, `correct`, `duplicate` | Manager, academics | Submit, review, correct attendance and resolve duplicate cases |
| `payment` | Manager, finance | Record money actually received |
| `import-batch` | Manager, finance | Import student class charges for reviewed attendance IDs |
| `task`, `complete-task` | Manager, sales | Create/complete team tasks |

The charge-import queue is currently rendered on Overview and Attendance, which Finance cannot open. Use the manager's reviewed queue workflow; the existence of the Finance-authorized action is not a claim that Finance has a corresponding visible queue page.

Similarly, the timetable's reschedule, cancel and extra-class links lead to the composite workspace, which Academics cannot open. Academics can use the dedicated matching/allocation forms, but should hand those existing-class changes to a manager until the navigation gap is addressed.

### Business actions

Path: `/erp/units/{unit}/business-actions/{action}`.

| Action values | Roles besides site administrator | Purpose |
|---|---|---|
| `student-update` | Manager, academics | Update operational student fields |
| `task-update`, `task-item`, `task-item-complete`, `followup` | Manager, sales | Edit tasks, checklists and follow-up evidence |
| `salary-paid`, `salary-reverse` | Manager, finance | Record/reverse an already-paid teacher settlement |
| `receipt-reverse`, `receipt-correct` | Manager, finance | Audited receipt reversals/corrections |
| `request-create`, `request-cancel`, `request-allocate` | Manager, finance | Payment requests and receipt allocation |
| `membership-grant`, `membership-revoke`, `teacher-link`, `teacher-unlink` | Site administrator only | Team permissions and explicit teacher-account links |
| `admission-create-lead`, `admission-update-lead` | Manager, sales | Create/edit enquiries within permitted ownership |
| `admission-schedule-demo`, `admission-update-demo` | Manager, sales | CRM demo tracking |
| `admission-followup`, `admission-reminder`, `admission-complete-reminder` | Manager, sales | Contact records and reminders |
| `admission-transfer`, `admission-resolve-transfer` | Manager, sales | Ownership transfer, subject to service rules |
| `admission-convert` | Manager, sales | Convert to student; optional genuine initial payment posts a receipt |
| `allocation-demo`, `allocation-feedback` | Manager, academics, sales | Checked timed demo booking and feedback |
| `allocation-qualify`, `allocation-window`, `allocation-regular` | Manager, academics | Verified qualifications/availability and regular class allocation |
| `teacher-candidate-create`, `teacher-candidate-update`, `teacher-candidate-contact` | Manager | Recruitment profile and contact history |
| `teacher-candidate-register`, `teacher-candidate-approve`, `teacher-candidate-resolve` | Manager | Registration, approval and identity resolution |

An Admissions initial payment must not be recorded again in Money. Recruitment registration does not automatically create login access. Saving contact evidence does not send an email or WhatsApp message; recording a payment does not transfer money.

### Private tuition operations

Path: `/erp/units/{unit}/tuition/{operation}`. Site administrator/team manager only.

| Operation | Purpose |
|---|---|
| `note` | Private class/student follow-up note |
| `parent-review` | Share/review/dispute individual attendance evidence |
| `monthly-review` | Record sharing of a monthly batch with snapshot checks and objection deadline |
| `candidate` | Private HR candidate record; separate from the shared recruitment pipeline |
| `interview` | Private interview record |

### Personal teacher and task actions

- `POST /erp/teacher/attendance`: linked teacher submits own lesson attendance after the class ends.
- `POST /erp/teacher/results`: linked teacher records results for an active assigned enrollment.
- `POST /erp/my-tasks/{task}/complete`: assignee completes their own task with current team access.
- `POST /erp/my-tasks/{task}/items/{item}/complete`: assignee completes their own checklist item. Read-only membership cannot complete tasks.

## CSV exports: expand `{dataset}`

Path: `GET /erp/units/{unit}/exports/{dataset}`. Supported datasets:

`students`, `enrollments`, `lessons`, `attendance`, `ledger`, `tasks`, `marks`, `payment_requests`, `leads`, `lead_activities`, `demos`, `lead_reminders`, `lead_transfers`, `teacher_enquiries`, `student_profiles`, `teacher_profiles`, `invoices`, `invoice_lines`, `task_templates`, `migration_history`.

| Role | Allowed supported datasets |
|---|---|
| Site administrator, manager, read-only | All above, subject to team access |
| Academics | `marks`, `enrollments`, `lessons`, `attendance` |
| Finance | `ledger`, `payment_requests` |
| Sales/follow-ups | `tasks` |
| Teacher | No team-wide dataset export via this endpoint |

The Admissions report view is not permission to export every CRM dataset. The existence of `invoices` exports does not imply a complete invoice-generator UI. `/erp/units/{unit}/reconciliation` provides application totals, not certification that Sheets reconcile or cutover is approved.

## Useful query parameters

| Page | Parameters and meaning |
|---|---|
| Business pages / composite workspace | `q`: search text, max 100 characters; tables use separate pagination keys |
| Admissions | `q`; `status=LEAD\|JOINED\|LOST`; `followup=today\|overdue\|unscheduled`; `lead_id`; `report=1` for the reporting view |
| Teacher recruitment | `q`, `stage`, `subject`, `syllabus`, `medium`, `availability`, `candidate`; allowed stages are checked by the application |
| Teacher matching | `student_id` or `lead_id`, `demo_id`, `subject_id`, `prepare_teacher_id`, `teacher_q`, `q`, `search=1`, `include_unverified=1`, `date`, `time`, `timezone`, `duration_minutes`, `medium`, `grade`, `syllabus`; use generated forms for valid combinations |
| Timetable | `date` (`YYYY-MM-DD`), `student`, `teacher`, `status=scheduled\|completed\|cancelled\|rescheduled\|no_show` |
| Academics and academic results export | `q`, `from`, `through` (`YYYY-MM-DD`); results screen also uses `results_page` and `subjects_page` |
| Teacher workspace | `section=overview\|timetable\|students\|attendance\|results\|reports`; `from`, `through` (`YYYY-MM-DD`, maximum 366-day span); `student` (assigned numeric ID); `results_page` |
| From Sheets | `unit` (numeric ID), `source=students\|teachers\|attendance\|balances\|timetable`, `q` (exact identity code); site administrators may use `review=1` |
| Parent statement | Required `student_id` and `month=YYYY-MM`; signed-in private workspace access required |
| Lookup endpoint | Required `type`, optional `q`; returns permitted form choices, not unrestricted directory access |

Lookup types: common `students`, `teachers`, `subjects`; academic `enrollments`, `lessons`, `agreements`, `attendance`, `reviews`; finance `requests`, `payments`, `salary`, `attendance`; sales `tasks`, `items`, `members`. Managers/read-only get the combined permitted types; site administrator-only account lookups include `users` and `revocable_members`.

## Registered route inventory

Routes below are extracted from the router, including compatibility and historical routes. Handler names omit `App\Http\Controllers\`; middleware classes omit `App\Http\Middleware\`. An em dash for route name means unnamed.

### Authentication and account security (18)

Portal-specific login pages share the same POST `/login`. Follow the account type checks; there is no public registration.

| Methods | Path | Route name | Handler | Declared middleware |
|---|---|---|---|---|
| `GET\|HEAD` | `/login` | `login` | `Auth\LoginController@showLoginForm` | `web` |
| `POST` | `/login` | `—` | `Auth\LoginController@login` | `web` |
| `POST` | `/logout` | `logout` | `Auth\LoginController@logout` | `web` |
| `GET\|HEAD` | `/password/reset` | `password.request` | `Auth\ForgotPasswordController@showLinkRequestForm` | `web` |
| `POST` | `/password/email` | `password.email` | `Auth\ForgotPasswordController@sendResetLinkEmail` | `web` |
| `GET\|HEAD` | `/password/reset/{token}` | `password.reset` | `Auth\ResetPasswordController@showResetForm` | `web` |
| `POST` | `/password/reset` | `password.update` | `Auth\ResetPasswordController@reset` | `web` |
| `GET\|HEAD` | `/password/confirm` | `password.confirm` | `Auth\ConfirmPasswordController@showConfirmForm` | `web` |
| `POST` | `/password/confirm` | `—` | `Auth\ConfirmPasswordController@confirm` | `web` |
| `GET\|HEAD` | `/superadmin/login` | `superadmin.login` | `Auth\LoginController@showSuperadminLogin` | `web` |
| `GET\|HEAD` | `/staff/login` | `staff.login` | `Auth\LoginController@showStaffLogin` | `web` |
| `GET\|HEAD` | `/backend` | `—` | `Auth\LoginController@showLoginForm` | `web` |
| `GET\|HEAD` | `/two-factor` | `two-factor.challenge` | `Auth\TwoFactorController@challenge` | `web, guest` |
| `POST` | `/two-factor` | `two-factor.verify` | `Auth\TwoFactorController@verify` | `web, guest, throttle:6,1` |
| `GET\|HEAD` | `/account/security` | `account.security` | `Auth\TwoFactorController@settings` | `web, auth` |
| `POST` | `/account/two-factor/setup` | `two-factor.setup` | `Auth\TwoFactorController@setup` | `web, auth, throttle:6,1` |
| `POST` | `/account/two-factor/enable` | `two-factor.enable` | `Auth\TwoFactorController@enable` | `web, auth, throttle:6,1` |
| `POST` | `/account/two-factor/disable` | `two-factor.disable` | `Auth\TwoFactorController@disable` | `web, auth, throttle:6,1` |

### Current ERP screens and actions (34)

Use the role journeys in the user guide. Personal teacher routes are not team-wide staff screens.

| Methods | Path | Route name | Handler | Declared middleware |
|---|---|---|---|---|
| `GET\|HEAD` | `/` | `erp.index` | `Erp\WorkspaceController@index` | `web, auth` |
| `GET\|HEAD` | `/tools` | `tools.index` | `Erp\LegacyToolsController@index` | `web, auth` |
| `GET\|HEAD` | `/erp/from-sheets` | `erp.sheets` | `Erp\SheetTransitionController@index` | `web, auth` |
| `GET\|HEAD` | `/teacher` | `teacher.portal` | `Erp\TeacherController@entry` | `web, auth` |
| `GET\|HEAD` | `/erp/my-tasks` | `erp.tasks.mine` | `Erp\OwnTasksController@index` | `web, auth, TeamAuthorization` |
| `POST` | `/erp/my-tasks/{task}/complete` | `erp.tasks.complete` | `Erp\OwnTasksController@complete` | `web, auth, TeamAuthorization` |
| `POST` | `/erp/my-tasks/{task}/items/{item}/complete` | `erp.tasks.item.complete` | `Erp\OwnTasksController@completeItem` | `web, auth, TeamAuthorization` |
| `POST` | `/erp/teacher/results` | `erp.teacher.result` | `Erp\TeacherController@result` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/teacher` | `erp.teacher.index` | `Erp\TeacherController@index` | `web, auth, TeamAuthorization` |
| `POST` | `/erp/teacher/attendance` | `erp.teacher.submit` | `Erp\TeacherController@submit` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}/academics/export` | `erp.academics.export` | `Erp\AcademicsController@export` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}/students/{student}` | `erp.student-profile` | `Erp\StudentProfileController@show` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}/business/{page?}` | `erp.business` | `Erp\BusinessController@page` | `web, auth, TeamAuthorization` |
| `POST` | `/erp/units/{unit}/business-actions/{action}` | `erp.business.mutate` | `Erp\BusinessController@mutate` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}/exports/{dataset}` | `erp.export` | `Erp\BusinessController@export` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}/reconciliation` | `erp.reconciliation` | `Erp\BusinessController@reconciliation` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}/lookup` | `erp.lookup` | `Erp\BusinessController@lookup` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}/payment-requests/{id}` | `erp.request.print` | `Erp\BusinessController@requestPrint` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}` | `erp.workspace` | `Erp\WorkspaceController@workspace` | `web, auth, TeamAuthorization` |
| `POST` | `/erp/units/{unit}/{action}` | `erp.mutate` | `Erp\WorkspaceController@mutate` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/units/{unit}/receipts/{entry}` | `erp.receipt` | `Erp\WorkspaceController@receipt` | `web, auth, TeamAuthorization` |
| `GET\|HEAD` | `/erp/administration` | `erp.administration` | `Erp\AdministrationController@index` | `web, auth, admin` |
| `POST` | `/erp/administration/teams` | `erp.teams.create` | `Erp\AdministrationController@team` | `web, auth, admin` |
| `POST` | `/erp/administration/teams/{id}` | `erp.teams.update` | `Erp\AdministrationController@team` | `web, auth, admin` |
| `POST` | `/erp/administration/teams/{id}/archive` | `erp.teams.archive` | `Erp\AdministrationController@archive` | `web, auth, admin` |
| `POST` | `/erp/administration/onboard` | `erp.onboard` | `Erp\AdministrationController@onboard` | `web, auth, admin` |
| `POST` | `/erp/administration/accounts/{id}` | `erp.accounts.update` | `Erp\AdministrationController@account` | `web, auth, admin, throttle:20,1` |
| `GET\|HEAD` | `/erp/administration/operations` | `erp.operations` | `Erp\AdministrationController@operations` | `web, auth, admin` |
| `GET\|HEAD` | `/erp/migration-center` | `erp.migration.center` | `Erp\MigrationCenterController@index` | `web, auth, admin` |
| `POST` | `/erp/migration-center/preview` | `erp.migration.preview` | `Erp\MigrationCenterController@preview` | `web, auth, admin, throttle:10,1` |
| `POST` | `/erp/migration-center/execute` | `erp.migration.execute` | `Erp\MigrationCenterController@execute` | `web, auth, admin, throttle:5,1` |
| `GET\|HEAD` | `/erp/units/{unit}/tuition` | `erp.tuition` | `Erp\TuitionController@index` | `web, auth` |
| `GET\|HEAD` | `/erp/units/{unit}/tuition/statement` | `erp.tuition.statement` | `Erp\TuitionController@statement` | `web, auth` |
| `POST` | `/erp/units/{unit}/tuition/{operation}` | `erp.tuition.mutate` | `Erp\TuitionController@mutate` | `web, auth` |

### Compatibility redirects (3)

`/home` and `/erp` redirect to `/`; `/legacy` redirects to `/tools`. The router registers these redirects for multiple methods; use GET for navigation.

| Methods | Path | Route name | Handler | Declared middleware |
|---|---|---|---|---|
| `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS` | `/home` | `home` | `\Illuminate\Routing\RedirectController` | `web` |
| `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS` | `/legacy` | `legacy.index` | `\Illuminate\Routing\RedirectController` | `web` |
| `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS` | `/erp` | `erp.compatibility` | `\Illuminate\Routing\RedirectController` | `web` |

### Historical administration tools (84)

These routes require site administration and are not the normal team-manager workspace. `/admin/delete/{route}/{id}` is a legacy deletion-confirmation page, not an ordinary report. It is blocked in Sheets-authoritative mode. Old `sync*` actions are separate from the temporary read-only Sheets bridge.

| Methods | Path | Route name | Handler | Declared middleware |
|---|---|---|---|---|
| `POST` | `/admin/maintenance-on` | `maintenance.on` | `admin\CommonController@maintenance_on` | `web, auth:web, admin` |
| `POST` | `/admin/maintenance-off` | `maintenance.off` | `admin\CommonController@maintenance_off` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/settings` | `settings.index` | `SettingsController@index` | `web, auth:web, admin` |
| `POST` | `/admin/update-settings` | `—` | `SettingsController@update` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/roles` | `roles.index` | `admin\RoleController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/roles/create` | `roles.create` | `admin\RoleController@create` | `web, auth:web, admin` |
| `POST` | `/admin/roles` | `roles.store` | `admin\RoleController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/roles/{role}/edit` | `roles.edit` | `admin\RoleController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/roles/{role}` | `roles.update` | `admin\RoleController@update` | `web, auth:web, admin` |
| `DELETE` | `/admin/roles/{role}` | `roles.destroy` | `admin\RoleController@destroy` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/users` | `users.index` | `admin\UsersController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/users/create` | `users.create` | `admin\UsersController@create` | `web, auth:web, admin` |
| `POST` | `/admin/users` | `users.store` | `admin\UsersController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/users/{user}/edit` | `users.edit` | `admin\UsersController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/users/{user}` | `users.update` | `admin\UsersController@update` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/delete/{route}/{id}` | `delete` | `admin\CommonController@delete` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/subjects` | `subjects.index` | `admin\SubjectController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/subjects/create` | `subjects.create` | `admin\SubjectController@create` | `web, auth:web, admin` |
| `POST` | `/admin/subjects` | `subjects.store` | `admin\SubjectController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/subjects/{subject}/edit` | `subjects.edit` | `admin\SubjectController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/subjects/{subject}` | `subjects.update` | `admin\SubjectController@update` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/topics` | `topics.index` | `admin\TopicController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/topics/create` | `topics.create` | `admin\TopicController@create` | `web, auth:web, admin` |
| `POST` | `/admin/topics` | `topics.store` | `admin\TopicController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/topics/{topic}/edit` | `topics.edit` | `admin\TopicController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/topics/{topic}` | `topics.update` | `admin\TopicController@update` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/profile` | `profile` | `admin\UsersController@Profile` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/teachers` | `teachers.index` | `admin\GoogleSpreadsheetController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/students` | `students.index` | `admin\StudentsController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/marklist` | `marklist.index` | `admin\MarklistController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/marklist/create` | `marklist.create` | `admin\MarklistController@create` | `web, auth:web, admin` |
| `POST` | `/admin/marklist` | `marklist.store` | `admin\MarklistController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/marklist/{marklist}/edit` | `marklist.edit` | `admin\MarklistController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/marklist/{marklist}` | `marklist.update` | `admin\MarklistController@update` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/master` | `master.index` | `admin\MasterController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/monthlyreport` | `monthlyreport.index` | `admin\WorkHourReportController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/workhour` | `workhour.index` | `WorkHourController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/workhour/create` | `workhour.create` | `WorkHourController@create` | `web, auth:web, admin` |
| `POST` | `/admin/workhour` | `workhour.store` | `WorkHourController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/workhour/{workhour}` | `workhour.show` | `WorkHourController@show` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/workhour/{workhour}/edit` | `workhour.edit` | `WorkHourController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/workhour/{workhour}` | `workhour.update` | `WorkHourController@update` | `web, auth:web, admin` |
| `DELETE` | `/admin/workhour/{workhour}` | `workhour.destroy` | `WorkHourController@destroy` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/studentinfoedit` | `studentinfoedit` | `WorkHourController@getStudentedit` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/teacherinfo` | `teacherinfo` | `admin\MarklistController@getTeacherInfo` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/studentinfo` | `studentinfo` | `WorkHourController@getStudentinfo` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/workreport` | `workreport.index` | `TeacherReportController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/workreport/create` | `workreport.create` | `TeacherReportController@create` | `web, auth:web, admin` |
| `POST` | `/admin/workreport` | `workreport.store` | `TeacherReportController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/workreport/{workreport}` | `workreport.show` | `TeacherReportController@show` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/workreport/{workreport}/edit` | `workreport.edit` | `TeacherReportController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/workreport/{workreport}` | `workreport.update` | `TeacherReportController@update` | `web, auth:web, admin` |
| `DELETE` | `/admin/workreport/{workreport}` | `workreport.destroy` | `TeacherReportController@destroy` | `web, auth:web, admin` |
| `POST` | `/admin/syncTeachers` | `syncTeachers` | `admin\GoogleSpreadsheetController@sheetData` | `web, auth:web, admin` |
| `POST` | `/admin/syncStudents` | `syncStudents` | `admin\StudentsController@sheetData` | `web, auth:web, admin` |
| `POST` | `/admin/syncMasters` | `syncMasters` | `admin\MasterController@MastersSynkdata` | `web, auth:web, admin` |
| `POST` | `/admin/syncTeacherReportData` | `syncTeacherReportData` | `TeacherReportController@syncTeacherReportData` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/marklistreport` | `marklistreport.index` | `MarklistReportController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/marklistreport/create` | `marklistreport.create` | `MarklistReportController@create` | `web, auth:web, admin` |
| `POST` | `/admin/marklistreport` | `marklistreport.store` | `MarklistReportController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/marklistreport/{marklistreport}` | `marklistreport.show` | `MarklistReportController@show` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/marklistreport/{marklistreport}/edit` | `marklistreport.edit` | `MarklistReportController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/marklistreport/{marklistreport}` | `marklistreport.update` | `MarklistReportController@update` | `web, auth:web, admin` |
| `DELETE` | `/admin/marklistreport/{marklistreport}` | `marklistreport.destroy` | `MarklistReportController@destroy` | `web, auth:web, admin` |
| `POST` | `/admin/syncMarklistReportData` | `syncMarklistReportData` | `MarklistReportController@syncMarklistReportData` | `web, auth:web, admin` |
| `POST` | `/admin/syncWorkHoureReportData` | `syncWorkHoureReportData` | `WorkHourController@syncWorkHoureReportData` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/duereport` | `duereport.index` | `DueController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/duereport/create` | `duereport.create` | `DueController@create` | `web, auth:web, admin` |
| `POST` | `/admin/duereport` | `duereport.store` | `DueController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/duereport/{duereport}` | `duereport.show` | `DueController@show` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/duereport/{duereport}/edit` | `duereport.edit` | `DueController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/duereport/{duereport}` | `duereport.update` | `DueController@update` | `web, auth:web, admin` |
| `DELETE` | `/admin/duereport/{duereport}` | `duereport.destroy` | `DueController@destroy` | `web, auth:web, admin` |
| `POST` | `/admin/syncDueReportData` | `syncDueReportData` | `DueController@syncDueReportData` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/timetable` | `timetable.index` | `TimeTableController@index` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/timetable/create` | `timetable.create` | `TimeTableController@create` | `web, auth:web, admin` |
| `POST` | `/admin/timetable` | `timetable.store` | `TimeTableController@store` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/timetable/{timetable}` | `timetable.show` | `TimeTableController@show` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/timetable/{timetable}/edit` | `timetable.edit` | `TimeTableController@edit` | `web, auth:web, admin` |
| `PUT\|PATCH` | `/admin/timetable/{timetable}` | `timetable.update` | `TimeTableController@update` | `web, auth:web, admin` |
| `DELETE` | `/admin/timetable/{timetable}` | `timetable.destroy` | `TimeTableController@destroy` | `web, auth:web, admin` |
| `POST` | `/admin/syncTimeTableData` | `syncTimeTableData` | `TimeTableController@syncTimeTableData` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/studentTimetable` | `studentTimetable` | `TimeTableController@studentTimetable` | `web, auth:web, admin` |
| `GET\|HEAD` | `/admin/work-hours/export` | `work_hours.export` | `WorkHourController@exportWorkHours` | `web, auth:web, admin` |

### Historical forms and reports (21)

Legacy teacher forms use their own credential/validation flow; a route listing containing only `web` does not establish unrestricted data access. Use the linked `/erp/teacher` portal for the new teacher journey. Do not treat these paths as student or parent self-service.

| Methods | Path | Route name | Handler | Declared middleware |
|---|---|---|---|---|
| `GET\|HEAD` | `/timetableReport` | `timetableReport` | `TimeTableController@timetableReport` | `web` |
| `GET\|HEAD` | `/timetable` | `timetable` | `TimeTableController@timetableReport` | `web` |
| `POST` | `/get-teacher-dob` | `get-teacher-dob` | `TimeTableController@getTeacherDOB` | `web, throttle:teacher-credentials` |
| `GET\|POST\|HEAD` | `/student-attendance-report` | `student-attendance-report` | `WorkHourController@studentsAttendanceReport` | `web, auth:web, admin, throttle:teacher-credentials` |
| `POST` | `/get-teachers` | `get-teachers` | `WorkHourController@getTeachersData` | `web, auth:web, admin, throttle:teacher-credentials` |
| `POST` | `/get-teacher` | `get-teacher` | `WorkHourController@getTeachers` | `web, auth:web, admin, throttle:teacher-credentials` |
| `POST` | `/get-students` | `get-students` | `WorkHourController@getStudentData` | `web, auth:web, admin, throttle:teacher-credentials` |
| `POST` | `/get-student` | `get-student` | `WorkHourController@getStudent` | `web, auth:web, admin, throttle:teacher-credentials` |
| `GET\|POST\|HEAD` | `/attendanceReport` | `attendanceReport` | `WorkHourController@attendanceReport` | `web, auth:web, admin, throttle:teacher-credentials` |
| `POST` | `/get-students-mark` | `get-students-mark` | `MarklistReportController@getStudentData` | `web, auth:web, admin, throttle:teacher-credentials` |
| `GET\|POST\|HEAD` | `/markreport` | `markreport` | `MarklistReportController@MarklistReport` | `web, auth:web, admin, throttle:teacher-credentials` |
| `GET\|HEAD` | `/attendance` | `attendance` | `WorkHourController@frontetHourUploadCreate` | `web` |
| `POST` | `/studentinfom` | `studentinfom` | `WorkHourController@getStudentinfo` | `web, web, throttle:teacher-credentials` |
| `POST` | `/upload-workhour` | `upload-workhour` | `WorkHourController@store` | `web, web, throttle:teacher-credentials` |
| `GET\|HEAD` | `/resultform` | `resultform` | `MarkListFrontentController@frontetMarkUploadCreate` | `web` |
| `POST` | `/studentinforesult` | `studentinforesult` | `MarkListFrontentController@getStudentinfo` | `web, web, throttle:teacher-credentials` |
| `POST` | `/upload-result` | `upload-result` | `MarkListFrontentController@store` | `web, web, throttle:teacher-credentials` |
| `GET\|HEAD` | `/performance` | `performenceform` | `PerformanceController@create` | `web` |
| `POST` | `/performnce-upload` | `performnce-upload` | `PerformanceController@store` | `web, web, throttle:teacher-credentials` |
| `POST` | `/submit-application` | `submit-application` | `MarkListFrontentController@store` | `web, web, throttle:teacher-credentials` |
| `GET\|HEAD` | `/legacy/dashboard` | `legacy.dashboard` | `HomeController@index` | `web` |

### Technical and framework endpoints (4)

`/health` is a basic application health route; `/ready` checks database readiness. `/api/user` is a legacy authenticated API endpoint, not an ERP integration API. The Sanctum endpoint is framework-provided. Cloudflare Worker/container control and private storage handling live outside this Laravel inventory; they are not staff navigation routes.

| Methods | Path | Route name | Handler | Declared middleware |
|---|---|---|---|---|
| `GET\|HEAD` | `/sanctum/csrf-cookie` | `sanctum.csrf-cookie` | `Laravel\Sanctum\Http\Controllers\CsrfCookieController@show` | `web` |
| `GET\|HEAD` | `/api/user` | `—` | `Closure` | `api, auth:api` |
| `GET\|HEAD` | `/ready` | `—` | `Closure` | `web` |
| `GET\|HEAD` | `/health` | `—` | `Closure` | `web` |

## Verification sources

The registered inventory was read after bootstrapping Laravel against an in-memory SQLite test configuration, without running controllers or querying live records. Permission descriptions were checked against [ERP routes](../routes/erp.php), [web routes](../routes/web.php), [TeamPermissions](../app/Modules/Erp/Services/TeamPermissions.php), [TeamAuthorization](../app/Http/Middleware/TeamAuthorization.php), relevant ERP controllers/services and [SheetAuthority](../app/Http/Middleware/SheetAuthority.php). The JSON export includes route parameter constraints. This is a code snapshot; regenerate and review it when routes or permissions change.
