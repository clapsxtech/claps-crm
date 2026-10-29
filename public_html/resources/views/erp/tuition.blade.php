@extends('erp.layout')
@section('content')
<h1>Class follow-up and HR</h1>
<p>{{ $business->name }} · Private to admins and team managers</p>
<p><a href="{{ route('erp.business',[$business->id,'dashboard']) }}">Back to dashboard</a></p>
<nav class="tabs"><a href="#review">Parent review</a><a href="#class-notes">Internal notes</a><a href="#hr">Teacher recruitment</a></nav>
<section id="review"><h2>Monthly parent statement</h2>
<form method="get" action="{{ route('erp.tuition.statement',$business->id) }}"><label>Student<select name="student_id" required>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }} · {{ $student->code }}</option>@endforeach</select></label><label>Month<input type="month" name="month" value="{{ now($business->timezone)->format('Y-m') }}" required></label><button>Prepare parent statement</button></form>
<h2>Attendance awaiting review</h2>
<p>Share the monthly statement using your existing parent communication channel. Record sharing evidence and the objection deadline here. Silence counts only after that deadline. Disputes block approval. This screen does not send messages or transfer money.</p>
@forelse($pending as $row)
<article class="card"><h3>{{ $row->student_name }} · Lesson #{{ $row->lesson_id }}</h3>
<p>{{ $row->actual_minutes }} minutes · Actual start: {{ $row->actual_starts_at ?? $row->starts_at }} UTC · {{ $row->submitted_late ? 'Submitted after the teaching day' : 'Submitted on the teaching day' }}</p>
<p>Parent review: {{ $row->parent_review_status }} @if($row->parent_deadline_at) · Deadline {{ $row->parent_deadline_at }} UTC @endif</p>
@if($row->change_reason)<p>Time change: {{ $row->change_reason }}</p>@endif
<form method="post" action="{{ route('erp.tuition.mutate',[$business->id,'parent-review']) }}">@csrf
<input type="hidden" name="attendance_id" value="{{ $row->id }}">
<label>Review update<select name="decision"><option value="share">Statement shared with parent</option><option value="accepted">Parent accepted / dispute resolved</option><option value="disputed">Parent disputed this entry</option></select></label>
<label>Objection deadline (UTC; required when sharing)<input name="deadline_at" placeholder="YYYY-MM-DD HH:MM:SS"></label>
<label>Sharing evidence, parent reply or resolution<textarea name="note" maxlength="4000" required></textarea></label><button>Save parent review</button></form>
<form method="post" action="{{ route('erp.mutate',[$business->id,'approve']) }}">@csrf<input type="hidden" name="attendance_id" value="{{ $row->id }}"><button>Approve reviewed teaching for earnings</button></form></article>
@empty<p>No attendance waiting for review.</p>@endforelse
{{ $pending->links('pagination::simple-bootstrap-4') }}
</section>
<section id="class-notes"><h2>Internal notes and demo outcomes</h2><p>Optional notes stay private. “Try another teacher” records a fit preference, not a performance complaint.</p>
<form method="post" action="{{ route('erp.tuition.mutate',[$business->id,'note']) }}">@csrf
<label>Class<select name="lesson_id" required><option value="">Choose a class</option>@foreach($lessons as $lesson)<option value="{{ $lesson->id }}">#{{ $lesson->id }} · {{ $lesson->student_name }} / {{ $lesson->teacher_name }} · {{ $lesson->starts_at }} UTC · {{ $lesson->kind }}</option>@endforeach</select></label>
<label>Record type<select name="kind"><option value="internal">Internal note</option><option value="demo">Demo outcome</option></select></label>
<label>Demo outcome<select name="outcome"><option value="">Not a demo outcome</option><option value="continue">Continue with this teacher</option><option value="try_another">Try another teacher</option><option value="awaiting_feedback">Awaiting feedback</option></select></label>
<label>Note (optional for demo outcome)<textarea name="note" maxlength="4000" placeholder="For example: teacher joined late; called and connected them."></textarea></label><button>Save private update</button></form>
@foreach($notes as $note)<p><strong>Lesson #{{ $note->lesson_id }} · {{ str_replace('_',' ',$note->outcome ?? $note->kind) }}</strong> · {{ $note->note }}<br><small>{{ $note->actor_name }} · {{ $note->created_at }} UTC</small></p>@endforeach
<h3>Shortened classes to check for makeup time</h3>
@forelse($shortfalls as $shortfall)<p>{{ $shortfall->name }} · Lesson #{{ $shortfall->id }} · {{ (int) \Carbon\Carbon::parse($shortfall->starts_at)->diffInMinutes(\Carbon\Carbon::parse($shortfall->ends_at)) - $shortfall->actual_minutes }} minutes below the planned duration. Check linked replacement lessons before scheduling more time.</p>@empty<p>No shortened classes in the latest 200 attendance records.</p>@endforelse
<h3>Recent classes needing follow-up</h3>
@foreach($lessons as $lesson)
@if(in_array($lesson->status,['cancelled','no_show']) || ($lesson->status==='scheduled' && \Carbon\Carbon::parse($lesson->ends_at,'UTC')->isPast()))
<p>#{{ $lesson->id }} · {{ $lesson->student_name }} / {{ $lesson->teacher_name }} · {{ $lesson->status }} · {{ $lesson->starts_at }} UTC — check what happened; a missing submission does not prove a missed class. <a href="{{ route('erp.workspace',$business->id) }}#schedule">Manage timetable</a></p>
@endif
@endforeach
<p>Showing the latest 200 classes and 100 internal updates. Use the timetable for older classes. Cancelled slots do not charge or earn pay; arrange replacement teaching through the timetable.</p>
</section>
<section id="hr"><h2>Teacher recruitment</h2><p>Record existing website applications here, then interview candidates when subject demand arises. Ready candidates still need explicit teacher onboarding and eligibility before assignment.</p>
<details><summary>Add candidate</summary><form method="post" action="{{ route('erp.tuition.mutate',[$business->id,'candidate']) }}">@csrf
@foreach(['name'=>'Name','contact'=>'Phone or email','qualification'=>'Qualifications','subjects'=>'Subjects','levels'=>'Teaching levels: LP / UP / high school / higher secondary','languages'=>'Languages','availability'=>'Available days and times'] as $field=>$label)<label>{{ $label }}<input name="{{ $field }}" maxlength="1000" @required(in_array($field,['name','contact','qualification','subjects','levels']))></label>@endforeach<button>Save candidate</button></form></details>
<details><summary>Record interview</summary><form method="post" action="{{ route('erp.tuition.mutate',[$business->id,'interview']) }}">@csrf
<label>Candidate<select name="candidate_id" required>@foreach($candidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }} · {{ $candidate->subjects }} · {{ $candidate->status }}</option>@endforeach</select></label>
<label>Score (0–100)<input type="number" name="score" min="0" max="100" required></label><label>Recording link (HTTPS)<input type="url" name="recording_url"></label><label>Strengths<textarea name="strengths" maxlength="4000"></textarea></label><label>Concerns<textarea name="concerns" maxlength="4000"></textarea></label>
<label>Decision<select name="decision"><option value="ready">Ready for onboarding</option><option value="hold">Keep in pool / follow up</option><option value="rejected">Do not proceed</option></select></label><button>Save interview</button></form></details>
@foreach($candidates as $candidate)<article><h3>{{ $candidate->name }} · {{ $candidate->status }}</h3><p>{{ $candidate->subjects }} · {{ $candidate->levels }} · {{ $candidate->languages }}<br>{{ $candidate->qualification }}<br>{{ $candidate->availability }} · {{ $candidate->contact }}</p>
@foreach($interviews->where('candidate_id',$candidate->id) as $interview)<p>Interview: {{ $interview->score }}/100 · {{ $interview->decision }} · {{ $interview->created_at }} UTC<br>Strengths: {{ $interview->strengths }}<br>Concerns: {{ $interview->concerns }} @if($interview->recording_url)<a href="{{ $interview->recording_url }}" rel="noopener noreferrer" target="_blank">Interview recording</a>@endif</p>@endforeach</article>@endforeach
<p>Showing the latest 200 candidates and interviews.</p></section>
@endsection
