@extends('erp.layout')
@section('content')
<h2>My lessons and attendance</h2>
<p>{{ $teacher->name }} · Times shown in {{ $teacher->timezone }}.</p>
<p class="notice">Submit actual minutes only for classes you delivered. Submission stays pending until an administrator imports the attendance batch; it does not charge the student. Cancelled lessons cannot receive attendance.</p>
<form method="get" action="{{ route('erp.teacher.index') }}">
<label>From (UTC date)<input type="date" name="from" value="{{ $from }}" required></label>
<label>Through (UTC date)<input type="date" name="through" value="{{ $through }}" required></label>
<button type="submit">Show my lessons</button>
</form>
@if($truncated)<p class="notice">Showing the first 500 lessons. Narrow the date range to see the remaining lessons.</p>@endif
<div class="scroll"><table><thead><tr><th>Lesson</th><th>Student</th><th>Subject</th><th>Starts</th><th>Ends</th><th>Status</th><th>Attendance</th></tr></thead><tbody>
@forelse($lessons as $lesson)
<tr><td>#{{ $lesson->id }} · {{ $lesson->kind }}</td><td>{{ $lesson->student_name }} ({{ $lesson->student_code }})</td><td>{{ $lesson->subject_name }}</td><td>{{ \Carbon\Carbon::parse($lesson->starts_at,'UTC')->setTimezone($teacher->timezone)->format('d M Y H:i') }}</td><td>{{ \Carbon\Carbon::parse($lesson->ends_at,'UTC')->setTimezone($teacher->timezone)->format('d M Y H:i') }}</td><td>{{ $lesson->status }}</td><td>
@if($lesson->attendance_id)
{{ $lesson->actual_minutes }} minutes · {{ $lesson->attendance_status }}<br><small>Ask an administrator for any correction.</small>
@elseif(in_array($lesson->status,['scheduled','completed']) && \Carbon\Carbon::parse($lesson->ends_at,'UTC')->isPast())
<form method="post" action="{{ route('erp.teacher.submit') }}">@csrf<input type="hidden" name="lesson_id" value="{{ $lesson->id }}"><label>Actual delivered minutes<input type="number" name="actual_minutes" min="1" max="480" required aria-label="Actual minutes for lesson {{ $lesson->id }}"></label><button type="submit">Submit attendance</button></form>
@else
<small>Attendance unavailable until a delivered lesson ends.</small>
@endif
</td></tr>
@empty<tr><td colspan="7">No lessons in this date range.</td></tr>@endforelse
</tbody></table></div>
@endsection
