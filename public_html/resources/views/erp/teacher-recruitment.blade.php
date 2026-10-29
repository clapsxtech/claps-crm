@extends('erp.layout')
@section('content')
@php
    $directoryFilters = request()->only(['q','stage','subject','syllabus','medium','availability','page']);
    $directoryUrl = route('erp.business',[$business->id,'teacher-recruitment']);
    $directoryQuery = \Illuminate\Support\Arr::query($directoryFilters);
@endphp
@if($selected)
<div class="page-heading"><div><div class="eyebrow">{{ $business->name }}</div><h1>Teacher profile</h1><p><a href="{{ $directoryUrl.($directoryQuery ? '?'.$directoryQuery : '') }}">Back to teacher profiles</a></p></div></div>
@else
<div class="page-heading"><div><div class="eyebrow">{{ $business->name }}</div><h1>Teacher recruitment &amp; directory</h1><p>Shared teacher pool: review applications, record registration and find teachers for classes and demos.</p></div></div>

<nav class="workflow-actions" aria-label="Recruitment shortcuts">
@if($manage)<a class="button" href="#action-new-application">@include('erp.icon',['name'=>'plus']) New application</a>@endif
<a class="button button-secondary" href="#teacher-profiles">Teacher profiles</a>
</nav>
<form method="get" action="{{ $directoryUrl }}#teacher-profiles" class="form-grid search-panel">
    <label>Name, phone or teacher code<input type="search" enterkeyhint="search" placeholder="Name, phone or code" name="q" value="{{ request('q') }}" maxlength="100"></label>
    <label>Stage<select name="stage"><option value="">{{ $manage ? 'All stages' : 'Registered directory' }}</option>@foreach($manage ? \App\Modules\Erp\Services\TeacherRecruitment::STAGES : ['REGISTERED','APPROVED'] as $stage)<option @selected(request('stage')===$stage)>{{ $stage }}</option>@endforeach</select></label>
    <details class="filter-options form-wide" @if(request()->filled('subject') || request()->filled('syllabus') || request()->filled('medium') || request()->filled('availability')) open @endif><summary>More filters: subject, syllabus &amp; availability</summary><div class="form-grid">
    @foreach(['subject'=>'Subject','syllabus'=>'Syllabus','medium'=>'Medium','availability'=>'Preferred time'] as $field=>$label)<label>{{ $label }}<input name="{{ $field }}" value="{{ request($field) }}" maxlength="100"></label>@endforeach
    </div></details>
    <button>Search teachers</button><a href="{{ route('erp.business',[$business->id,'teacher-recruitment']) }}">Clear filters</a>
</form>

<section id="teacher-profiles"><h2>Teacher profiles <span class="count-pill">{{ $candidates->total() }}</span></h2><div class="scroll"><table class="mobile-records"><thead><tr><th>Teacher</th><th>Contact</th><th>Teaching profile</th><th>Availability</th><th>Status</th>@if($manage)<th>Review</th>@endif</tr></thead><tbody>
@forelse($candidates as $c)
<tr><td><strong>{{ $c->name }}</strong><br>{{ $c->teacher_code }}<br><small>{{ $c->qualification }}</small></td><td>@if($c->phone)@include('erp.phone-link',['phone'=>$c->phone])@else Contact needs review @endif<br>{{ $c->email }}</td><td>{{ $c->subjects }}<br><small>{{ $c->grades }} · {{ $c->syllabuses }} · {{ $c->medium }}</small></td><td>{{ $c->availability ?: 'Confirm with teacher' }}</td><td><span class="status-badge">{{ $c->stage }}</span><br><small>Source: {{ $c->source_status ?: 'Not set' }}</small>@if($c->operational_status)<br><small>ERP: {{ $c->operational_status }}</small>@endif</td>@if($manage)<td><a href="{{ $directoryUrl.'?'.\Illuminate\Support\Arr::query(array_merge($directoryFilters,['candidate'=>$c->id])) }}">Open profile</a></td>@endif</tr>
@empty<tr><td colspan="6">No matching teachers.</td></tr>@endforelse
</tbody></table></div>{{ $candidates->links() }}</section>
@if($manage)
<details id="action-new-application"><summary>New application</summary><form method="post" action="{{ route('erp.business.mutate',[$business->id,'teacher-candidate-create']) }}">@csrf<div class="form-grid"><label>Name<input name="name" required maxlength="255"></label><label>Phone<input type="tel" name="phone" required maxlength="40"></label><label>Email<input type="email" name="email"></label><label>Qualification<input name="qualification"></label><label>Subjects<input name="subjects"></label><label>CV link<input type="url" name="cv_url"></label></div><button>Save application</button></form></details>
@endif
<details><summary>Registration &amp; class assignment</summary><p class="notice">Registration and availability do not authorize class assignment. Approval links the profile to the existing teacher pool; subject eligibility and timetable confirmation still apply.</p></details>
@endif
@if($selected)
<section><h2>{{ $selected->name }} (#{{ $selected->id }})</h2><p>{{ $selected->stage }} · {{ $selected->teacher_code }}</p>
@if($selected->merged_into_id)<p>This profile was merged. <a href="{{ route('erp.business',[$business->id,'teacher-recruitment','candidate'=>$selected->merged_into_id]) }}">Open the surviving profile</a>.</p>@endif
@if(!$selected->merged_into_id)
<details @if($selected->stage==='REVIEW') open @endif><summary>Resolve identity / compare duplicate profiles</summary>
<p>Correct the contact details or deliberately merge this profile into an existing profile. Source rows and histories are retained. Distinct operational teacher records cannot be merged here.</p>
@foreach($duplicates as $d)<p><a href="{{ route('erp.business',[$business->id,'teacher-recruitment','candidate'=>$d->id]) }}">#{{ $d->id }} {{ $d->name }}</a> · {{ $d->phone }} · {{ $d->teacher_code }} · {{ $d->stage }}</p>@endforeach
<form method="post" action="{{ route('erp.business.mutate',[$business->id,'teacher-candidate-resolve']) }}">@csrf<input type="hidden" name="candidate_id" value="{{ $selected->id }}"><input type="hidden" name="decision" value="correct"><label>Verified name<input name="name" value="{{ $selected->name }}" required maxlength="255"></label><label>Verified phone<input type="tel" name="phone" value="{{ $selected->phone }}" required maxlength="40"></label><label>Verified teacher code (clear an incorrect, unlinked code)<input name="teacher_code" value="{{ $selected->teacher_code }}" maxlength="100"></label><label>Identity verification evidence<textarea name="note" required maxlength="4000"></textarea></label><button>Resolve corrected identity</button></form>
<details><summary>Merge duplicate profile</summary><form method="post" action="{{ route('erp.business.mutate',[$business->id,'teacher-candidate-resolve']) }}">@csrf<input type="hidden" name="candidate_id" value="{{ $selected->id }}"><input type="hidden" name="decision" value="merge"><label>Surviving profile ID<input type="number" name="target_id" min="1" required></label><label>Evidence these are the same teacher<textarea name="note" required maxlength="4000"></textarea></label><button>Merge this profile into the chosen profile</button></form></details>
</details>
@if($selected->stage==='REVIEW')<p class="notice">This record needs identity review. Compare its source rows and contact details before selecting or registering it. Existing teacher links cannot be reassigned here.</p>@endif
@if($selected->cv_url && preg_match('#^https?://#i',$selected->cv_url))<a href="{{ $selected->cv_url }}" target="_blank" rel="noopener noreferrer">Open CV</a>@endif
<form method="post" action="{{ route('erp.business.mutate',[$business->id,'teacher-candidate-update']) }}">@csrf<input type="hidden" name="candidate_id" value="{{ $selected->id }}"><div class="form-grid">
@foreach(['email'=>'Email','qualification'=>'Qualification','subjects'=>'Subjects','grades'=>'Classes','syllabuses'=>'Syllabuses','medium'=>'Medium','availability'=>'Preferred times','cv_url'=>'CV link'] as $field=>$label)<label>{{ $label }}<input name="{{ $field }}" value="{{ $selected->$field }}" maxlength="{{ $field==='cv_url' ? 2000 : 4000 }}"></label>@endforeach
@if(!in_array($selected->stage,['REGISTERED','APPROVED']))<label>Application stage<select name="stage"><option value="">Keep current stage</option>@foreach(['APPLIED','CONTACTED','SELECTED','REJECTED'] as $stage)<option @selected($selected->stage===$stage)>{{ $stage }}</option>@endforeach</select></label>@endif
<label>Review note<textarea name="note" maxlength="4000"></textarea></label></div><button>Save review</button></form>
<details><summary>Record contact / follow-up</summary><form method="post" action="{{ route('erp.business.mutate',[$business->id,'teacher-candidate-contact']) }}">@csrf<input type="hidden" name="candidate_id" value="{{ $selected->id }}"><label>Contact outcome<textarea name="note" required maxlength="4000"></textarea></label><label>Next follow-up<input type="date" name="next_follow_up_on"></label><button>Save contact</button></form></details>
@if($selected->stage==='SELECTED')<form method="post" action="{{ route('erp.business.mutate',[$business->id,'teacher-candidate-register']) }}">@csrf<input type="hidden" name="candidate_id" value="{{ $selected->id }}"><button>Confirm registration completed</button></form>@endif
@if(in_array($selected->stage,['REGISTERED','APPROVED']))<form method="post" action="{{ route('erp.business.mutate',[$business->id,'teacher-candidate-approve']) }}">@csrf<input type="hidden" name="candidate_id" value="{{ $selected->id }}"><label>Teacher code<input name="teacher_code" value="{{ $selected->teacher_code }}" required maxlength="100"></label><button>Approve and link to teacher pool</button></form>@endif
@if($selected->teacher_id && (new \App\Modules\Erp\Services\TeamPermissions())->allows(auth()->user(),$business->id,'action','allocation-qualify'))<p><a href="{{ route('erp.business',[$business->id,'allocation','prepare_teacher_id'=>$selected->teacher_id]) }}#prepare">Confirm subject eligibility and availability for matching</a></p>@endif
@endif
<h3>Review history</h3>@forelse($activities as $a)<p>{{ $a->created_at }} · {{ $a->kind }}<br>{{ $a->note }}@if($a->next_follow_up_on)<br>Follow up: {{ $a->next_follow_up_on }}@endif</p>@empty<p>No contacts recorded yet.</p>@endforelse
<h3>Imported source records (up to 30)</h3>@foreach($sources as $source)<details><summary>{{ $source->kind }} · {{ $source->source_key }}</summary>@foreach(array_intersect_key(json_decode($source->payload,true)?:[],array_flip(['name','code','phone','email','source_status','submitted_at'])) as $field=>$value)<p>{{ $field }}: {{ $value }}</p>@endforeach</details>@endforeach
</section>
@endif
@endsection
