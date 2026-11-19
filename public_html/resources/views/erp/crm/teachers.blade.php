@php
    $directory = \App\Modules\Erp\Services\TeacherDirectory::class;
    $pipeline = \App\Modules\Erp\Services\SalesPipeline::class;
    $canClassify = $permissions->allows(auth()->user(), $business->id, 'action', 'teacher-classify');
    $parentWhatsapp = $profileLead ? $pipeline::contact($profileLead)['whatsapp'] : null;
    $filterKeys = ['teacher_q','level','category','subject_id','sort'];
@endphp
<div class="crm-section-heading"><div><h2>Teachers</h2><p>Find a teacher, call or message them, and send their profile to a parent. TT = weekly timetable hours.</p></div><span class="crm-count">{{ $teacherDirectory->total() }}</span></div>
@if($profileLead)<p class="crm-profile-target">@include('erp.icon',['name'=>'chat']) Sending profiles to the parent of <strong>{{ $pipeline::name($profileLead) }}</strong> · <a href="{{ $crmUrl(['lead_id'=>$profileLead->id]) }}">Back to enquiry</a></p>@endif
<nav class="crm-priorities crm-level-tabs" aria-label="Teacher level"><a href="{{ $crmUrl(array_filter(['view'=>'teachers','for_lead'=>request('for_lead')] + request()->only(['teacher_q','category','subject_id','sort']))) }}" @if(!request('level')) aria-current="page" @endif>All levels</a>@foreach($directory::LEVELS as $key=>$label)<a href="{{ $crmUrl(array_filter(['view'=>'teachers','for_lead'=>request('for_lead'),'level'=>$key] + request()->only(['teacher_q','category','subject_id','sort']))) }}" @if(request('level')===$key) aria-current="page" @endif title="{{ $label }}">{{ $key }}</a>@endforeach</nav>
<form method="get" class="crm-search crm-teacher-filters" role="search" aria-label="Filter teachers">
    <input type="hidden" name="view" value="teachers">@if(request('for_lead'))<input type="hidden" name="for_lead" value="{{ request('for_lead') }}">@endif
    <label class="crm-search-field">@include('erp.icon',['name'=>'search'])<span class="crm-sr-only">Teacher name or CT code</span><input type="search" name="teacher_q" value="{{ request('teacher_q') }}" placeholder="Search name or CT code" maxlength="100" enterkeyhint="search"></label><button type="submit" class="crm-search-button">Search</button>
    <details class="crm-search-filters" @if(request()->hasAny(['category','subject_id','sort'])) open @endif><summary>More filters &amp; order @if(request('category') || request('subject_id'))<span>Active</span>@endif</summary><div class="form-grid">
        <label>Level<select name="level"><option value="">All levels</option>@foreach($directory::LEVELS as $key=>$label)<option value="{{ $key }}" @selected(request('level')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label>Category<select name="category"><option value="">All categories</option>@foreach($directory::CATEGORIES as $category)<option @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></label>
        <label>Subject<select name="subject_id"><option value="">All subjects</option>@foreach($teacherSubjects as $subject)<option value="{{ $subject->id }}" @selected((string) request('subject_id')===(string) $subject->id)>{{ $subject->name }}</option>@endforeach</select></label>
        <label>Order<select name="sort">@foreach($directory::SORTS as $key=>$label)<option value="{{ $key }}" @selected(request('sort','code')===$key)>{{ $label }}</option>@endforeach</select></label>
        <button>Apply filters</button>
    </div></details>
    @if(array_filter(request()->only($filterKeys)))<a class="crm-clear" href="{{ $crmUrl(array_filter(['view'=>'teachers','for_lead'=>request('for_lead')])) }}">Clear filters</a>@endif
</form>
<div class="crm-lead-grid" id="crm-teachers">@forelse($teacherDirectory as $teacher)
@php($contact = $pipeline::teacherContact($teacher->phone))
<article class="crm-teacher-card">
    <div class="crm-card-top"><div class="crm-lead-identity"><span class="crm-tt-badge" title="{{ $teacher->tt_hours }} timetable hours per week"><strong>{{ $teacher->tt_hours }}</strong><small>TT hrs</small></span><span><strong>{{ $teacher->name }}</strong><small>{{ $teacher->code }} · Registered {{ $teacher->registered_sort ? \Carbon\Carbon::parse($teacher->registered_sort)->format('d M Y') : 'date not recorded' }}</small></span></div>@if($teacher->category)<span class="crm-stage-badge crm-category-{{ strtolower($teacher->category) }}">{{ $teacher->category }}</span>@endif</div>
    <div class="crm-level-chips">@forelse($teacher->level_list as $level)<span class="crm-level-chip">{{ $level }}</span>@empty<span>Level not set</span>@endforelse</div>
    <dl class="crm-facts crm-teacher-facts">
        <div><dt>TT hours</dt><dd>{{ $teacher->tt_hours }} h / week</dd></div>
        <div><dt>Subjects</dt><dd>{{ $teacher->subjects ?: 'Not recorded' }}</dd></div>
        <div><dt>Classes</dt><dd>{{ $teacher->classes ?: 'Not confirmed' }}</dd></div>
        <div><dt>Medium</dt><dd>{{ $teacher->mediums ?: 'Not confirmed' }}</dd></div>
        @if($teacher->qualification)<div><dt>Qualification</dt><dd>{{ $teacher->qualification }}</dd></div>@endif
    </dl>
    @if($canClassify)<details class="crm-action-panel"><summary>@include('erp.icon',['name'=>'edit']) Set level &amp; category</summary><form method="post" class="crm-form" action="{{ route('erp.business.mutate',[$business->id,'teacher-classify']) }}">@csrf<input type="hidden" name="teacher_id" value="{{ $teacher->id }}"><fieldset class="crm-channel"><legend>Levels</legend>@foreach($directory::LEVELS as $key=>$label)<label><input type="checkbox" name="levels[]" value="{{ $key }}" @checked(in_array($key,$teacher->level_list,true))> {{ $key }}</label>@endforeach</fieldset><label>Category<select name="category"><option value="">Not set</option>@foreach($directory::CATEGORIES as $category)<option @selected($teacher->category===$category)>{{ $category }}</option>@endforeach</select></label><button class="crm-save">Save</button></form></details>@endif
    <div class="crm-card-actions">
        @if($contact['dial'])<a href="tel:{{ $contact['dial'] }}" aria-label="Call {{ $teacher->name }}">@include('erp.icon',['name'=>'phone']) Call</a>@endif
        @if($contact['whatsapp'])<a class="crm-whatsapp" href="{{ $contact['whatsapp'] }}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp {{ $teacher->name }}">@include('erp.icon',['name'=>'chat']) WhatsApp</a>@endif
        <a class="crm-primary-action" href="{{ $pipeline::whatsappMessage($parentWhatsapp, $pipeline::teacherProfileMessage($teacher,$business)) }}" target="_blank" rel="noopener noreferrer">@include('erp.icon',['name'=>'external']) Send profile</a>
    </div>
</article>
@empty<div class="crm-empty">@include('erp.icon',['name'=>'people'])<h3>No teachers match</h3><p>Try another level, category or subject. Levels and categories are set by the academics team.</p><a class="button button-secondary" href="{{ $crmUrl(['view'=>'teachers']) }}">Show all teachers</a></div>@endforelse</div>
{{ $teacherDirectory->fragment('crm-teachers')->links('pagination::simple-bootstrap-4') }}
