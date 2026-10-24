<section aria-labelledby="daily-attendance-title">
<h3 id="daily-attendance-title">Attendance action queues</h3>
<p>Two separate decisions: importing attended non-demo classes charges the student; approving attendance accrues teacher earnings. Neither action transfers money. Read each named record before submitting.</p>
@foreach(['student_import'=>'Student consumption awaiting import','teacher_approval'=>'Teacher approval and accrual review'] as $queue=>$title)
<h4>{{ $title }}</h4>
<p>{{ $queues[$queue]->total() }} records · Oldest class first · Times in {{ $business->timezone }}</p>
@if($queue==='student_import')<p><small>Demo classes are free and do not appear in this charge queue. Duplicate candidates remain importable under the confirmed policy; review and correction remain separate.</small></p>@endif
<div class="scroll"><table><thead><tr><th>Student and class</th><th>Teacher</th><th>Attended</th><th>Review</th><th>Next action</th></tr></thead><tbody>
@forelse($queues[$queue] as $record)
<tr><td><strong>{{ $record->student_name }}</strong> · {{ $record->student_code }}<br>{{ $record->subject_name }}<br>{{ \Carbon\CarbonImmutable::parse($record->starts_at,'UTC')->setTimezone($business->timezone)->format('j M Y H:i P') }}<br><small>Lesson #{{ $record->lesson_id }} · Attendance #{{ $record->id }}</small></td>
<td>{{ $record->teacher_name }}</td><td>{{ $record->actual_minutes }} minutes<br>{{ ucfirst($record->kind) }}@if($record->kind==='demo')<br><small>Free and unpaid</small>@endif</td>
<td>@if($record->pending_reviews)<strong>{{ $record->pending_reviews }} pending duplicate review(s)</strong><br><a href="{{ route('erp.workspace',$business->id) }}#action-duplicate">Record teacher reply and resolve</a>@else No pending duplicate review @endif</td>
<td>@if($queue==='student_import')
<form method="post" action="{{ route('erp.mutate',[$business->id,'import-batch']) }}">@csrf
<input type="hidden" name="return_page" value="{{ $page }}"><input type="hidden" name="attendance_ids[]" value="{{ $record->id }}">
<label>Stable import reference<input name="reference" maxlength="120" required placeholder="Unique reviewed batch reference"></label>
<button>Import this class for student charge</button></form>
@elseif($record->pending_reviews)<p>Resolve duplicate review before teacher approval.</p>
@else<form method="post" action="{{ route('erp.mutate',[$business->id,'approve']) }}">@csrf
<input type="hidden" name="return_page" value="{{ $page }}"><input type="hidden" name="attendance_id" value="{{ $record->id }}">
<button>{{ $record->kind==='demo'?'Approve free, unpaid demo':($record->status==='approved'?'Recheck approved teacher accrual':'Approve attended teacher earnings') }}</button></form>@endif
</td></tr>
@empty<tr><td colspan="5">No records awaiting this action.</td></tr>@endforelse
</tbody></table></div>
{{ $queues[$queue]->links('pagination::simple-bootstrap-4') }}
@endforeach
<p><small>Queues are read-only snapshots. Submission rechecks current permissions, duplicates, migration cutoff and financial idempotency. Another administrator may already have acted; successful replays never charge or accrue twice.</small></p>
</section>
