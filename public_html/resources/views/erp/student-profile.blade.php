@extends('erp.layout')
@section('content')
<p><a href="{{ route('erp.business', [$business->id, 'people']) }}">{{ $business->name }} · Students &amp; teachers</a></p>
<h2>{{ $student->name }}</h2>
<p>{{ $student->code }} · {{ $student->status }} · Grade {{ $student->grade }} · {{ $student->syllabus }}</p>
@if((new \App\Modules\Erp\Services\TeamPermissions())->allows(auth()->user(),$business->id,'page','allocation'))<p><a href="{{ route('erp.business',[$business->id,'allocation','student_id'=>$student->id]) }}">Find a teacher / allocate classes</a></p>@endif
<p class="notice">Read-only student profile. Viewing this page does not import attendance, charge a student, accrue salary, send a message or move money.</p>
<nav class="tabs" aria-label="Student profile sections">
@foreach(['details'=>'Details','assignments'=>'Assignments','timetable'=>'Timetable','attendance'=>'Attendance','balance'=>'Balance & receipts','followups'=>'Follow-ups','tasks'=>'Tasks'] as $anchor=>$label)
<a href="#{{ $anchor }}">{{ $label }}</a>
@endforeach
</nav>
<section id="details" aria-labelledby="details-heading">
<h3 id="details-heading">Student details</h3>
@foreach($admissionHandoffs as $handoff)<details><summary>Admission handoff · enquiry #{{ $handoff->id }}</summary><p>Source: {{ $handoff->source ?: 'Not recorded' }} · Joined: {{ $handoff->joined_on }}</p>
@foreach(['learning_requirement'=>'Learning needs','concern'=>'Parent concerns','about_student'=>'About the student','about_parent'=>'Parent communication preferences'] as $field=>$label)<p><strong>{{ $label }}:</strong> {{ $handoff->$field ?: 'Not recorded' }}</p>@endforeach
</details>@endforeach
<p>Registered {{ $student->registered_on }} · Student timezone: {{ $student->timezone }}</p>
<p>Guardian contact: {{ $student->guardian_contact ?: 'Not recorded' }}</p>
<p>Meeting address: {{ $student->meeting_url ?: 'Not recorded' }}</p>
@if($sheetProfile)
<h4>Registration profile</h4>
<p>Section: {{ $sheetProfile->section ?: 'Not recorded' }} · Country: {{ $sheetProfile->country ?: 'Not recorded' }} · Hour rate: {{ $sheetProfile->hour_rate_paise !== null ? \App\Modules\Erp\Services\MoneyDisplay::inr((int)$sheetProfile->hour_rate_paise) : 'Not recorded' }}</p>
<p>WhatsApp: {{ $sheetProfile->whatsapp_number ?: 'Not recorded' }} · Student contact: {{ $sheetProfile->student_contact ?: 'Not recorded' }} · Email: {{ $sheetProfile->email ?: 'Not recorded' }}</p>
<p>Father: {{ $sheetProfile->father_name ?: 'Not recorded' }} · Mother: {{ $sheetProfile->mother_name ?: 'Not recorded' }}</p>
<p>School: {{ $sheetProfile->school ?: 'Not recorded' }} · Preferred medium: {{ $sheetProfile->preferred_medium ?: 'Not recorded' }} · Preferred time: {{ $sheetProfile->preferred_time ?: 'Not recorded' }}</p>
<p>Network: {{ $sheetProfile->network_connectivity ?: 'Not recorded' }} · Source: {{ $sheetProfile->source ?: 'Not recorded' }} · Imported source id: {{ $sheetProfile->source_external_id ?: 'Not recorded' }}</p>
@endif
</section>
<section id="assignments" aria-labelledby="assignments-heading">
<h3 id="assignments-heading">Named assignments</h3>
<div class="scroll"><table><caption>Teacher and subject assignments</caption><thead><tr><th scope="col">Teacher / subject</th><th scope="col">Dates</th><th scope="col">Status</th></tr></thead><tbody>
@forelse($assignments as $row)<tr><td>{{ $row->teacher_name }} / {{ $row->subject_name }}</td><td>{{ $row->starts_on }} to {{ $row->ends_on ?: 'Ongoing' }}</td><td>{{ $row->status }}</td></tr>@empty<tr><td colspan="3">No assignments recorded.</td></tr>@endforelse
</tbody></table></div>{{ $assignments->fragment('assignments')->links() }}
</section>
<section id="timetable" aria-labelledby="timetable-heading">
<h3 id="timetable-heading">Timetable</h3>
<h4>Recurring agreements</h4>
<div class="scroll"><table><caption>Each agreement uses its recorded timezone</caption><thead><tr><th scope="col">Teacher / subject</th><th scope="col">Time</th><th scope="col">Dates / status</th></tr></thead><tbody>
@forelse($agreements as $row)<tr><td>{{ $row->teacher_name }} / {{ $row->subject_name }}</td><td>{{ [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'][$row->weekday] ?? 'Unknown weekday' }} {{ $row->start_time }} · {{ $row->duration_minutes }} min · {{ $row->timezone }}</td><td>{{ $row->starts_on }} to {{ $row->ends_on ?: 'Ongoing' }} · {{ $row->status }}</td></tr>@empty<tr><td colspan="3">No recurring agreements recorded.</td></tr>@endforelse
</tbody></table></div>{{ $agreements->fragment('timetable')->links() }}
<h4>Lessons, newest first</h4>
<div class="scroll"><table><caption>Lesson times in {{ $business->timezone }}</caption><thead><tr><th scope="col">Teacher / subject</th><th scope="col">Start / end</th><th scope="col">Status</th></tr></thead><tbody>
@forelse($lessons as $row)<tr><td>{{ $row->teacher_name }} / {{ $row->subject_name }}</td><td>{{ \Carbon\CarbonImmutable::parse($row->starts_at, 'UTC')->setTimezone($business->timezone)->format('Y-m-d H:i') }} to {{ \Carbon\CarbonImmutable::parse($row->ends_at, 'UTC')->setTimezone($business->timezone)->format('Y-m-d H:i') }}</td><td>{{ $row->status }} · {{ $row->kind }}</td></tr>@empty<tr><td colspan="3">No lessons recorded.</td></tr>@endforelse
</tbody></table></div>{{ $lessons->fragment('timetable')->links() }}
</section>
<section id="attendance" aria-labelledby="attendance-heading">
<h3 id="attendance-heading">Attendance history</h3>
<p>Attendance status is not proof of a student charge or teacher payment.</p>
<div class="scroll"><table><caption>Recorded attendance · {{ $business->timezone }}</caption><thead><tr><th scope="col">Lesson</th><th scope="col">Minutes</th><th scope="col">Status</th></tr></thead><tbody>
@forelse($attendance as $row)<tr><td>{{ $row->teacher_name }} / {{ $row->subject_name }} · {{ \Carbon\CarbonImmutable::parse($row->starts_at, 'UTC')->setTimezone($business->timezone)->format('Y-m-d H:i') }}</td><td>{{ $row->actual_minutes }}</td><td>{{ $row->status }}</td></tr>@empty<tr><td colspan="3">No attendance recorded.</td></tr>@endforelse
</tbody></table></div>{{ $attendance->fragment('attendance')->links() }}
</section>
<section id="balance" aria-labelledby="balance-heading">
<h3 id="balance-heading">Student balance &amp; receipt history</h3>
<p>Balances include all recorded student entries, including reversals, not just the current page. Teacher payables are separate.</p>
@forelse($balances as $balance)<p>{{ $balance->currency }} {{ ltrim(substr(\App\Modules\Erp\Services\MoneyDisplay::inr((int)$balance->balance_paise), 4), '-') }} · {{ (int)$balance->balance_paise < 0 ? 'Due' : ((int)$balance->balance_paise > 0 ? 'Credit' : 'Settled') }}</p>@empty<p>No student ledger entries. Recorded balance: INR 0.00.</p>@endforelse
<div class="scroll"><table><caption>Immutable ledger history · installation time: {{ config('app.timezone') }}</caption><thead><tr><th scope="col">Reference / kind</th><th scope="col">Amount</th><th scope="col">Recorded</th></tr></thead><tbody>
@forelse($ledger as $row)<tr><td>@if($row->kind === 'payment')<a href="{{ route('erp.receipt', [$business->id, $row->id]) }}">{{ $row->reference }}</a>@else{{ $row->reference }}@endif · {{ $row->kind }}@if($row->reverses_id) · Reverses entry #{{ $row->reverses_id }}@endif</td><td>{{ $row->currency }} {{ substr(\App\Modules\Erp\Services\MoneyDisplay::inr((int)$row->amount_paise), 4) }}</td><td>{{ $row->occurred_at }}</td></tr>@empty<tr><td colspan="3">No receipt or ledger history recorded.</td></tr>@endforelse
</tbody></table></div>{{ $ledger->fragment('balance')->links() }}
<h4>Payment requests</h4><p>A request is not a receipt and does not change the balance.</p>
<div class="scroll"><table><caption>Requests and current recorded coverage</caption><thead><tr><th scope="col">Reference</th><th scope="col">Requested / outstanding</th><th scope="col">Due / status</th></tr></thead><tbody>
@forelse($requests as $row)@php($summary = $requestSummaries[$row->id])<tr><td><a href="{{ route('erp.request.print', [$business->id, $row->id]) }}">{{ $row->reference }}</a></td><td>INR {{ substr(\App\Modules\Erp\Services\MoneyDisplay::inr($summary['requested_paise']), 4) }} / INR {{ substr(\App\Modules\Erp\Services\MoneyDisplay::inr($summary['outstanding_paise']), 4) }}</td><td>{{ $row->due_on ?: 'No due date' }} · {{ $summary['status'] }}</td></tr>@empty<tr><td colspan="3">No payment requests recorded.</td></tr>@endforelse
</tbody></table></div>{{ $requests->fragment('balance')->links() }}
</section>
<section id="followups" aria-labelledby="followups-heading"><h3 id="followups-heading">Follow-up history</h3><p>Recorded conversations only. No outbound message is sent from this profile.</p>
@forelse($followups as $row)<article><h4>{{ $row->contact_channel }} · {{ $row->created_at }}</h4><p>{{ $row->note }}</p><p>Outcome: {{ $row->outcome }}</p><p>Next due: {{ $row->next_due_on ?: 'Not scheduled' }}</p></article>@empty<p>No follow-ups recorded.</p>@endforelse
{{ $followups->fragment('followups')->links() }}</section>
<section id="tasks" aria-labelledby="tasks-heading"><h3 id="tasks-heading">Student tasks</h3>
@forelse($tasks as $row)<article><h4>{{ $row->title }}</h4><p>{{ $row->status }} · Due {{ $row->due_on ?: 'Not scheduled' }} · {{ $labels['assigned_to'][$row->assigned_to] ?? 'Unassigned or unavailable account' }}</p><p>{{ $row->notes }}</p></article>@empty<p>No student tasks recorded.</p>@endforelse
{{ $tasks->fragment('tasks')->links() }}</section>
@endsection
