@extends('erp.layout')
@section('body-class', 'crm-screen')
@section('content')
@php
    $permissions = new \App\Modules\Erp\Services\TeamPermissions();
    $admissionsUrl = route('erp.business', [$business->id, 'admissions']);
    $crmUrl = fn(array $query = []) => $admissionsUrl.($query ? '?'.\Illuminate\Support\Arr::query($query) : '');
    $crmStages = \App\Modules\Erp\Services\SalesPipeline::STAGES;
    $crmCan = [];
    foreach (['create-lead','update-lead','followup','reminder','complete-reminder','convert','schedule-demo','update-demo','transfer','resolve-transfer'] as $action) $crmCan[$action] = $permissions->allows(auth()->user(), $business->id, 'action', 'admission-'.$action);
    $crmViews = ['pipeline'=>['Pipeline','grid'], 'today'=>['Today','check'], 'demos'=>['Demos','calendar'], 'reminders'=>['Reminders','clock'], 'transfers'=>['Transfers','people'], 'reports'=>['Reports','chart']];
@endphp
<div class="sales-crm">
<header class="crm-heading"><div><div class="eyebrow">{{ $business->name }} <span>SALES WORKSPACE</span></div><h1>Sales CRM<span class="crm-live-dot" aria-hidden="true"></span></h1><p>Your conversations. Your next steps. Your admissions.</p></div>@if($crmCan['create-lead'] && $crmView !== 'new')<a class="button crm-create" href="{{ $crmUrl(['view'=>'new']) }}">@include('erp.icon',['name'=>'plus']) New enquiry</a>@endif</header>
<nav class="crm-view-tabs" aria-label="Sales CRM sections">@foreach($crmViews as $key=>$item)<a class="crm-view-{{ $key }}" href="{{ $crmUrl(['view'=>$key]) }}" @if($crmView===$key && !$selectedLead) aria-current="page" @endif>@include('erp.icon',['name'=>$item[1]]){{ $item[0] }}@if($key==='transfers' && $transferCount)<span class="crm-nav-count">{{ $transferCount }}</span>@endif</a>@endforeach</nav>

@if($selectedLead)
    @include('erp.crm.detail', ['lead'=>$selectedLead])
@elseif($crmView === 'new')
    @include('erp.crm.new-enquiry')
@else
    @if(in_array($crmView,['pipeline','today']))
    <div class="crm-metrics">
        <a class="crm-metric crm-metric-blue" href="{{ $crmUrl(['view'=>'pipeline','status'=>'LEAD']) }}"><span>Active leads</span><strong>{{ $stats['Active leads'] }}</strong>@include('erp.icon',['name'=>'people'])</a>
        <a class="crm-metric crm-metric-orange" href="{{ $crmUrl(['view'=>'today']) }}"><span>Due today</span><strong>{{ $stats['Due today'] }}</strong>@include('erp.icon',['name'=>'clock'])</a>
        <a class="crm-metric crm-metric-green" href="{{ $crmUrl(['view'=>'reports','report_from'=>now($business->timezone)->startOfMonth()->toDateString(),'report_to'=>$today]) }}"><span>Joined this month</span><strong>{{ $stats['Joined this month'] }}</strong>@include('erp.icon',['name'=>'check'])</a>
    </div>
    @if($crmView==='today')
    <div class="crm-section-heading"><div><h2>Your next conversations</h2><p>{{ \Carbon\Carbon::parse($today)->format('l, d F') }} · {{ $business->timezone }}</p></div></div>
    <nav class="crm-priorities" aria-label="Follow-up priority">@foreach(['today'=>'Today','overdue'=>'Overdue','unscheduled'=>'No next action'] as $key=>$label)<a class="crm-priority-{{ $key }}" href="{{ $crmUrl(['view'=>'today','followup'=>$key]) }}" @if($followup===$key) aria-current="page" @endif>{{ $label }} <strong>{{ (int) $followupCounts->$key }}</strong></a>@endforeach</nav>
    @else
    <div class="crm-section-heading"><h2>Your pipeline</h2><a href="{{ $crmUrl(['view'=>'today','followup'=>'overdue']) }}" class="crm-overdue-link">{{ (int) $followupCounts->overdue }} overdue @include('erp.icon',['name'=>'arrow'])</a></div>
    <nav class="crm-stage-tabs" aria-label="Pipeline stage"><a href="{{ $crmUrl(['view'=>'pipeline']) }}" @if(!request('stage')) aria-current="page" @endif>All <strong>{{ array_sum($stageCounts) }}</strong></a>@foreach($crmStages as $key=>$label)<a class="crm-stage-{{ $key }}" href="{{ $crmUrl(['view'=>'pipeline','stage'=>$key]) }}" @if(request('stage')===$key) aria-current="page" @endif><span class="crm-stage-dot" aria-hidden="true"></span>{{ $label }} <strong>{{ $stageCounts[$key] }}</strong></a>@endforeach</nav>
    @endif
    <form method="get" class="crm-search" role="search" aria-label="Search enquiries">
        <input type="hidden" name="view" value="{{ $crmView }}">@if(request('stage'))<input type="hidden" name="stage" value="{{ request('stage') }}">@endif
        <label class="crm-search-field">@include('erp.icon',['name'=>'search'])<span class="crm-sr-only">Name, phone or admission code</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, phone or code" maxlength="100" enterkeyhint="search"></label><button type="submit" class="crm-search-button">Search</button>
        <details class="crm-search-filters" @if(request('hot') || request('status') || ($crmView==='pipeline' && request('followup'))) open @endif><summary>Filters @if(request('hot') || request('status') || request('followup'))<span>Active</span>@endif</summary><div class="form-grid"><label>Follow-up<select name="followup"><option value="">Any date</option>@foreach(['today'=>'Today','overdue'=>'Overdue','unscheduled'=>'No next action'] as $key=>$label)<option value="{{ $key }}" @selected($followup===$key)>{{ $label }}</option>@endforeach</select></label><label>Status<select name="status"><option value="">All statuses</option>@foreach(['LEAD'=>'Active','JOINED'=>'Registered','LOST'=>'Lost'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label><label class="crm-check"><input type="checkbox" name="hot" value="1" @checked(request('hot'))> Hot leads only</label><button>Apply filters</button></div></details>
        @if(request('q') || request('hot') || request('status') || request('followup'))<a class="crm-clear" href="{{ $crmUrl(['view'=>$crmView]) }}">Clear filters</a>@endif
    </form>
    <div id="admissions-leads" class="crm-results"><div class="crm-result-count"><strong>{{ $leads->total() }}</strong> {{ $leads->total()===1 ? 'enquiry' : 'enquiries' }}@if(request('q')) matching “{{ request('q') }}”@endif</div>
    <div class="crm-lead-grid">@forelse($leads as $lead)@include('erp.crm.lead-card')@empty<div class="crm-empty">@include('erp.icon',['name'=>'check'])<h3>{{ $crmView==='today' ? 'You’re clear here' : 'No enquiries to show' }}</h3><p>{{ $crmView==='today' ? 'Try another queue or check your reminders below.' : 'Try a different search, clear the filters, or add an enquiry.' }}</p><a class="button button-secondary" href="{{ $crmUrl(['view'=>'pipeline']) }}">View all enquiries</a></div>@endforelse</div>
    {{ $leads->fragment('admissions-leads')->links('pagination::simple-bootstrap-4') }}</div>
    @endif
    @if($demos !== null)@include('erp.crm.demos')@endif
    @if($reminders !== null)@include('erp.crm.reminders')@endif
    @if($crmView==='transfers')@include('erp.crm.transfers')@endif
    @if($crmView==='reports')
    <section class="crm-panel" id="action-sales-report"><div class="crm-section-heading"><div><h2>Your sales report</h2><p>Activity, follow-ups and campaign outcomes in one place.</p></div></div><form method="get" class="form-grid"><input type="hidden" name="view" value="reports"><input type="hidden" name="report" value="1"><label>From<input type="date" name="report_from" value="{{ $salesReport['dates']['report_from'] }}" required></label><label>To<input type="date" name="report_to" value="{{ $salesReport['dates']['report_to'] }}" required></label><button>Show report</button></form></section>
    @include('erp.sales-report',['report'=>$salesReport])
    @endif
@endif
</div>
@endsection
