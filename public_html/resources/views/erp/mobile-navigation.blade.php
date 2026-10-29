@php
    // Reuse the permission-filtered sidebar pages; shortcuts never grant new access.
    $mobilePages = [];
    if ($currentUnit) {
        $preferredPages = in_array($currentPage, ['teacher-recruitment', 'allocation'], true)
            ? ['teacher-recruitment', 'allocation']
            : (in_array($currentPage, ['admissions', 'followups'], true)
                ? ['admissions', 'followups'] : ['dashboard', 'attendance', 'academics', 'money', 'admissions']);
        foreach ($preferredPages as $key) {
            if (isset($navigationPages[$key])) $mobilePages[$key] = $navigationPages[$key];
            if (count($mobilePages) === 2) break;
        }
    }
    $mobileLabels = ['teacher-recruitment'=>'Teachers', 'allocation'=>'Matching', 'admissions'=>'Enquiries', 'followups'=>'Follow-ups', 'dashboard'=>'Overview', 'attendance'=>'Attendance', 'academics'=>'Academics', 'money'=>'Money'];
@endphp
@if($currentUnit && $currentPage==='admissions')
<nav class="mobile-navigation crm-bottom-navigation" aria-label="Quick navigation">
@foreach(['pipeline'=>['Pipeline','grid'],'today'=>['Today','check'],'demos'=>['Demos','calendar'],'reports'=>['Reports','chart']] as $key=>$item)
<a href="{{ route('erp.business',[$currentUnit->id,'admissions','view'=>$key]) }}" @if(($crmView ?? 'pipeline')===$key) aria-current="page" @endif>@include('erp.icon',['name'=>$item[1]])<span>{{ $item[0] }}</span></a>
@endforeach
<button type="button" data-navigation-toggle aria-controls="app-navigation" aria-expanded="false" aria-label="Open navigation">@include('erp.icon',['name'=>'menu'])<span>Menu</span></button>
</nav>
@else
<nav class="mobile-navigation" aria-label="Quick navigation">
    <a href="{{ route('erp.index') }}" @if($isHome) aria-current="page" @endif>@include('erp.icon',['name'=>'home'])<span>Home</span></a>
    @if($currentUnit)
        @foreach($mobilePages as $key=>$item)
        <a href="{{ route('erp.business',[$currentUnit->id,$key]) }}" @if($currentPage===$key) aria-current="page" @endif>@include('erp.icon',['name'=>$item[1]])<span>{{ $mobileLabels[$key] }}</span></a>
        @endforeach
    @elseif($toolAdministrator)
        <a href="{{ route('erp.administration') }}" @if(request()->routeIs('erp.administration')) aria-current="page" @endif>@include('erp.icon',['name'=>'people'])<span>Accounts</span></a>
        <a href="{{ route('erp.operations') }}" @if(request()->routeIs('erp.operations')) aria-current="page" @endif>@include('erp.icon',['name'=>'shield'])<span>Operations</span></a>
    @endif
    <a href="{{ route('erp.tasks.mine') }}" @if(request()->routeIs('erp.tasks.*')) aria-current="page" @endif>@include('erp.icon',['name'=>'check'])<span>My tasks</span></a>
    <button type="button" data-navigation-toggle aria-controls="app-navigation" aria-expanded="false" aria-label="Open navigation">@include('erp.icon',['name'=>'menu'])<span>Menu</span></button>
</nav>

@endif
