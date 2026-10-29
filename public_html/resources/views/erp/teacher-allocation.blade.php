@extends('erp.layout')
@section('content')
<div class="page-heading"><div><div class="eyebrow">{{ $business->name }}</div><h1>Teacher matching &amp; allocation</h1><p>Choose a learner and slot, compare confirmed teaching details, then book a demo or regular class.</p></div><a href="{{ route('erp.business',[$business->id,'teacher-recruitment','stage'=>'REVIEW']) }}">Review imported teacher identities</a></div>
<form method="get"><label>Find an enquiry<input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Name or admission code"></label><button>Find enquiry</button></form>
<form method="get" class="form-grid">
<label>Enquiry (latest 30 search results)<select name="lead_id"><option value="">Choose an enquiry</option>@foreach($leads as $lead)<option value="{{ $lead->id }}" @selected(($seed['lead_id']??null)==$lead->id)>{{ $lead->name }} (#{{ $lead->id }})</option>@endforeach</select></label>
<label>Or existing student<input type="search" class="entity-search" placeholder="Search student name or code" aria-label="Search existing student"><select name="student_id" class="entity-picker" data-url="{{ route('erp.lookup',[$business->id,'type'=>'students']) }}"><option value="">Choose a student</option>@if($context && !$context->lead_id)<option value="{{ $context->student_id }}" selected>{{ $context->name }}</option>@endif</select></label>
<button>Open learner</button></form>

@if($context)
@if($handoff)<section><h2>Learner handoff</h2>@foreach(['learning_requirement'=>'Learning needs','concern'=>'Parent concerns','about_student'=>'About the student','about_parent'=>'Parent communication preferences'] as $field=>$label)<p><strong>{{ $label }}:</strong> {{ $handoff->$field ?: 'Not recorded' }}</p>@endforeach</section>@endif
<section><h2>{{ $context->name }}</h2><p>{{ $context->grade ?: 'Confirm class' }} · {{ $context->syllabus ?: 'Confirm syllabus' }} · Learner timezone: {{ $context->timezone }}</p>
<form method="get" class="form-grid">
<input type="hidden" name="{{ $context->lead_id ? 'lead_id' : 'student_id' }}" value="{{ $context->lead_id ?: $context->student_id }}"><input type="hidden" name="search" value="1">
<label>Subject<select name="subject_id" required><option value="">Choose subject</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(($seed['subject_id']??null)==$subject->id)>{{ $subject->name }}</option>@endforeach</select></label>
<label>Class / grade<input name="grade" value="{{ $seed['grade']??'' }}" required maxlength="100" @if($context->grade) readonly @endif></label>
<label>Syllabus<input name="syllabus" value="{{ $seed['syllabus']??'' }}" required maxlength="100" @if($context->syllabus) readonly @endif></label>
<label>Teaching medium<select name="medium" required><option value="">Choose medium</option>@foreach(\App\Modules\Erp\Services\TeacherAllocation::MEDIA as $medium)<option @selected(($seed['medium']??null)===$medium)>{{ $medium }}</option>@endforeach</select></label>
<label>{{ $context->lead_id ? 'Demo / first regular class date' : 'First class date' }}<input type="date" name="date" value="{{ $seed['date']??'' }}" required></label><label>Start time<input type="time" name="time" value="{{ $seed['time']??'' }}" required></label>
<label>Timezone<input name="timezone" value="{{ $seed['timezone'] }}" required maxlength="100" placeholder="Asia/Kolkata"></label><label>Duration (minutes)<input type="number" name="duration_minutes" value="{{ $seed['duration_minutes'] }}" min="5" max="180" required></label>
<label>Teacher name / code<input name="teacher_q" value="{{ $seed['teacher_q']??'' }}" maxlength="100"></label><label><input type="checkbox" name="include_unverified" value="1" @checked(request('include_unverified'))> Include teachers needing eligibility verification</label><button>Find teachers for this slot</button>
</form></section>
@endif

@if($matching)
<section><h2>Teacher matches <span class="count-pill">{{ $matching['teachers']->total() }}</span></h2><p>Availability is checked against confirmed windows, exceptions, recurring agreements, lessons and demos. Imported free-text preferences do not establish availability. The slot is rechecked when saved.</p>
<div class="scroll"><table class="mobile-records"><thead><tr><th>Teacher</th><th>Teacher-local time</th><th>Scheduled teaching in 7 days from selected slot</th><th>Demo history in this team</th><th>Match / next step</th></tr></thead><tbody>
@foreach($matching['teachers'] as $teacher)
<tr><td><strong>{{ $teacher->name }}</strong><br>{{ $teacher->code }}<br><small>Teaching details verified: {{ $teacher->verified_at ?: 'Not yet' }}</small></td><td>{{ $teacher->local_time }}<br><small>{{ $teacher->timezone }}</small></td><td>{{ $teacher->hours }} hours<br><small>Scheduled lessons only; excludes demos.</small></td><td>@php($demoStats=$teacherDemoStats[$teacher->id]??null){{ $demoStats->booked ?? 0 }} booked · {{ $demoStats->attended ?? 0 }} attended · {{ $demoStats->allocated ?? 0 }} allocated<br><small>All-time recorded demos; allocated means linked to a regular enrollment. Not a rating.</small></td><td>
@if($teacher->blockers)
@foreach($teacher->blockers as $blocker)<p>{{ $blocker }}</p>@endforeach
@if($permissions->allows(auth()->user(),$business->id,'action','allocation-qualify'))<a href="{{ route('erp.business',array_merge([$business->id,'allocation'],$seed,['prepare_teacher_id'=>$teacher->id])) }}#prepare">Confirm teaching details / availability</a>@endif
@else
<p>Confirmed details match this slot.</p>
@if($context->lead_id && $permissions->allows(auth()->user(),$business->id,'action','allocation-demo'))
<details><summary>Book confirmed demo</summary><form method="post" action="{{ route('erp.business.mutate',[$business->id,'allocation-demo']) }}">@csrf
@include('erp.allocation-slot',['slot'=>$matching['slot'],'context'=>$context,'teacher'=>$teacher])
<label>Meeting link<input type="url" name="meet_link" maxlength="500"></label>@include('erp.allocation-confirmation')<button>Reserve demo slot</button></form></details>
@endif
@if($permissions->allows(auth()->user(),$business->id,'action','allocation-regular'))
@php($successful=$demos->filter(fn($d)=>$d->feedback_outcome==='CONTINUE' && $d->status==='ATTENDED' && $d->teacher_id==$teacher->id && $d->subject_id==$matching['slot']['subject_id']))
@if(!$context->lead_id || $successful->isNotEmpty())
<details><summary>Allocate weekly regular class</summary><form method="post" action="{{ route('erp.business.mutate',[$business->id,'allocation-regular']) }}">@csrf
@include('erp.allocation-slot',['slot'=>$matching['slot'],'context'=>$context,'teacher'=>$teacher])
@if($context->lead_id)<label>Successful demo<select name="demo_id" required>@foreach($successful as $demo)<option value="{{ $demo->id }}">{{ $demo->scheduled_on }} · {{ $demo->teacher_name }}</option>@endforeach</select></label>@endif
<p>Repeats weekly on {{ \Carbon\CarbonImmutable::parse($matching['slot']['date'])->format('l') }} at {{ $matching['slot']['time'] }} ({{ $matching['slot']['timezone'] }}), starting {{ $matching['slot']['date'] }}.</p>
<label>Last date (required, within 366 days)<input type="date" name="ends_on" min="{{ $matching['slot']['date'] }}" required></label><label>Student hourly rate (INR)<input name="student_rate_inr" inputmode="decimal" required></label><label>Teacher hourly rate (INR)<input name="teacher_rate_inr" inputmode="decimal" required></label>
@include('erp.allocation-confirmation',['recurring'=>true])<button>Confirm assignment &amp; generate lessons</button></form></details>
@else<p>Record Continue feedback after the demo before regular allocation.</p>@endif
@endif
@endif
</td></tr>
@endforeach
@if($matching['teachers']->isEmpty())<tr><td colspan="5">No verified subject / class / syllabus matches. Include teachers needing verification, or confirm their teaching details below.</td></tr>@endif
</tbody></table></div>{{ $matching['teachers']->links() }}</section>
@endif

@if($demos->isNotEmpty())
<section><h2>Demo feedback and handoff</h2><p>Latest 30 timed demos for this enquiry. Continue feedback enables regular allocation with the same teacher and subject.</p>
@foreach($demos as $demo)<article><h3>{{ $demo->teacher_name }} · {{ $demo->scheduled_on }} {{ $demo->scheduled_time }} ({{ $business->timezone }})</h3><p>{{ $demo->status }} · {{ $demo->feedback_outcome ?: 'Feedback not recorded' }}</p>
@if($demo->enrollment_id)<a href="{{ route('erp.business',[$business->id,'timetable']) }}">Open allocated timetable</a>
@elseif($permissions->allows(auth()->user(),$business->id,'action','allocation-feedback') && !in_array($demo->status,['CANCELLED','NO_SHOW']))
<details><summary>Record attended demo feedback</summary><form method="post" action="{{ route('erp.business.mutate',[$business->id,'allocation-feedback']) }}">@csrf<input type="hidden" name="demo_id" value="{{ $demo->id }}"><label>Parent preference<select name="outcome"><option value="CONTINUE">Continue with this teacher</option><option value="TRY_ANOTHER">Try another teacher</option><option value="AWAITING_FEEDBACK">Awaiting feedback</option></select></label><label>Feedback / attendance evidence<textarea name="note" required maxlength="4000"></textarea></label><button>Record feedback after demo</button></form></details>
@endif
@endforeach</section>
@endif

@if($permissions->allows(auth()->user(),$business->id,'action','allocation-qualify'))
<section id="prepare"><h2>Prepare a teacher for matching</h2><form method="get"><input type="hidden" name="prepare" value="1"><label>Teacher<input type="search" class="entity-search" placeholder="Search teacher name or code" aria-label="Search teacher to prepare"><select name="prepare_teacher_id" class="entity-picker" data-url="{{ route('erp.lookup',[$business->id,'type'=>'teachers']) }}" required><option value="">Choose teacher</option>@if($prepared)<option value="{{ $prepared->id }}" selected>{{ $prepared->name }} ({{ $prepared->code }})</option>@endif</select></label><button>Open teaching details</button></form>
@if($prepared)
<h3>{{ $prepared->name }} ({{ $prepared->code }})</h3><p>Operational status: {{ $prepared->status }}. Imported profile text is not copied into these confirmed fields.</p>
@foreach($eligibilities as $e)<p>{{ $e->name }} · {{ $e->grade }} · {{ $e->syllabus }}</p>@endforeach
<form method="post" action="{{ route('erp.business.mutate',[$business->id,'allocation-qualify']) }}">@csrf<input type="hidden" name="teacher_id" value="{{ $prepared->id }}">@include('erp.allocation-return')<div class="form-grid"><label>Confirm subject<select name="subject_id" required>@foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach</select></label><label>Class / grade<input name="grade" required maxlength="100"></label><label>Syllabus<input name="syllabus" required maxlength="100"></label></div>
<fieldset><legend>Confirmed teaching media (all currently supported)</legend>@foreach(\App\Modules\Erp\Services\TeacherAllocation::MEDIA as $medium)<label><input type="checkbox" name="mediums[]" value="{{ $medium }}" @checked(in_array($medium,json_decode($profile->mediums??'[]',true)?:[]))>{{ $medium }}</label>@endforeach</fieldset><label>Verification evidence<textarea name="note" required maxlength="4000"></textarea></label><button>Save confirmed teaching details</button></form>
<h3>Confirmed availability windows</h3>@forelse($windows as $w)<p>{{ [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'][$w->weekday] }} {{ $w->start_time }}–{{ $w->end_time }} · {{ $w->timezone }} · {{ $w->starts_on }} to {{ $w->ends_on ?: 'ongoing' }}</p>@empty<p>No structured availability recorded.</p>@endforelse
@include('erp.allocation-window',['teacherId'=>$prepared->id,'studentId'=>null,'personTimezone'=>$prepared->timezone])
@endif</section>
@endif
@if($context?->student_id && $permissions->allows(auth()->user(),$business->id,'action','allocation-window'))<section><h2>Confirm this student's availability</h2>@include('erp.allocation-window',['teacherId'=>null,'studentId'=>$context->student_id,'personTimezone'=>$context->timezone])</section>@endif
@endsection
