@php
    $toolGroups = [
        'Academic records' => [
            'topics' => ['Topics', 'Organize the chapters and topics used in historical assessment records.', 'topics.index', 'book'],
            'subjects' => ['Subjects', 'Maintain the subject catalogue for historical records.', 'subjects.index', 'book'],
            'marklist' => ['Assessment records', 'Review and manage recorded marks, subjects and topics.', 'marklist.index', 'check'],
            'master' => ['Assignment records', 'Review the original student and teacher assignments.', 'master.index', 'people'],
            'students' => ['Student records', 'Browse the original student directory.', 'students.index', 'people'],
            'teachers' => ['Teacher records', 'Browse the original teacher directory.', 'teachers.index', 'people'],
        ],
        'Historical reports' => [
            'marklistreport' => ['Assessment reports', 'Explore historical results with filters and downloadable reports.', 'marklistreport.index', 'book'],
            'monthlyreport' => ['Monthly reports', 'Review recorded teaching hours by month.', 'monthlyreport.index', 'calendar'],
            'workreport' => ['Teacher hour reports', 'Review historical teacher hours and performance.', 'workreport.index', 'calendar'],
            'workhour' => ['Work-hour records', 'Review and manage the original attendance and teaching-hour records.', 'workhour.index', 'check'],
            'duereport' => ['Historical dues', 'Review due reports from your original records.', 'duereport.index', 'wallet'],
            'timetable' => ['Historical timetable', 'Review the original teaching schedules.', 'timetable.index', 'calendar'],
            'studentTimetable' => ['Student timetable', 'Review the original student schedules.', 'studentTimetable', 'calendar'],
        ],
        'Site administration' => [
            'users' => ['User accounts', 'Manage site accounts and profiles. Business-unit access is configured separately.', 'users.index', 'people'],
            'roles' => ['Site roles', 'Manage the roles used by site accounts.', 'roles.index', 'shield'],
            'settings' => ['Application settings', 'Maintain your application identity and site configuration.', 'settings.index', 'shield'],
        ],
    ];
    $toolScreen = request()->is('admin/*') || request()->routeIs('markreport','attendanceReport','student-attendance-report','legacy.dashboard');
    $toolTitle = null;
    $toolGroup = 'Workspace tools';
    $toolDescription = '';
    $toolKey = request()->segment(2);
    foreach ($toolGroups as $group => $items) {
        if (isset($items[$toolKey]) && request()->is('admin/*')) {
            [$toolTitle, $toolDescription] = $items[$toolKey];
            $toolGroup = $group;
        }
    }
    if (request()->routeIs('roles.create')) $toolTitle = 'Create a site role';
    if (request()->routeIs('roles.edit')) $toolTitle = 'Edit site role';
    foreach (['markreport'=>'Assessment history','attendanceReport'=>'Attendance history','student-attendance-report'=>'Student attendance history','legacy.dashboard'=>'Records overview'] as $route => $title) {
        if (request()->routeIs($route)) { $toolTitle = $title; $toolGroup = 'Historical records'; $toolDescription = 'Review existing records with your current access permissions.'; }
    }
    $toolAdministrator = auth()->check() && (new \App\Modules\IdentityAccess\Services\SiteAccessPolicy())->isAdministrator(auth()->user());
@endphp

@php
    $navigationUnits = (new \App\Modules\Erp\Services\Access())->units(auth()->user());
    $currentUnit = $business ?? null;
    $navigationPages = ['dashboard'=>['Overview','home'],'admissions'=>['Admissions & CRM','chat'],'allocation'=>['Teacher matching','calendar'],'teacher-recruitment'=>['Teacher recruitment','people'],'people'=>['Students & teachers','people'],'academics'=>['Academics','book'],'timetable'=>['Timetable','calendar'],'attendance'=>['Attendance','check'],'money'=>['Money & receipts','wallet'],'followups'=>['Follow-ups','chat'],'staff'=>['Team access','shield'],'migration'=>['Migration & exports','download']];
    if (!$toolAdministrator && $currentUnit) $navigationPages=array_filter($navigationPages,fn($item,$key)=>(new \App\Modules\Erp\Services\TeamPermissions())->allows(auth()->user(),$currentUnit->id,'page',$key),ARRAY_FILTER_USE_BOTH);
    $currentPage = request()->route('page');
    $isHome = request()->routeIs('erp.index');
    $workspaceLabel = match (true) {
        request()->routeIs('erp.administration') => 'Teams & accounts',
        request()->routeIs('erp.operations') => 'Operations & audit',
        request()->routeIs('erp.tasks.*') => 'My assigned tasks',
        request()->routeIs('account.security*') => 'Account security',
        request()->routeIs('erp.sheets') => 'From Sheets',
        request()->routeIs('erp.migration.*') => 'Migration center',
        request()->routeIs('erp.teacher.*') => 'My lessons',
        request()->routeIs('legacy.*','tools.index') => 'Records & administration',
        default => 'Business units',
    };
    $displayName = auth()->user()->name;
    $userInitials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($displayName,0,1));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#117c66">
    <title>{{ $toolTitle ?? ($currentUnit ? ($navigationPages[$currentPage][0] ?? $currentUnit->name) : $workspaceLabel) }} · ClapsLearn Operations</title>
    @if($toolScreen) @include('erp.tool-assets') @endif
    <link rel="stylesheet" href="{{ mix('css/erp.css') }}">
    @stack('head')
    <script src="{{ mix('js/erp-shell.js') }}" defer></script>
</head>
<body class="erp-app @yield('body-class') {{ $toolScreen ? 'tool-screen' : '' }}">
<a class="skip-link" href="#main-content">Skip to main content</a>
<button class="nav-backdrop" type="button" aria-label="Close navigation" tabindex="-1" hidden></button>
<aside class="app-sidebar" id="app-navigation" aria-label="Main navigation">
    <a class="brand" href="{{ route('erp.index') }}"><span class="brand-symbol">@include('erp.icon',['name'=>'book'])</span><span>ClapsLearn<small>OPERATIONS</small></span></a>
    <div class="sidebar-section-label">WORKSPACE</div>
    <nav class="sidebar-nav" aria-label="Workspace navigation">
        <a href="{{ route('account.security') }}">Account security</a>
        @if(config('sheet_transition.enabled'))<a href="{{ route('erp.sheets', $currentUnit ? ['unit'=>$currentUnit->id] : []) }}">From Sheets</a>@endif
        @if($toolAdministrator)<a href="{{ route('erp.administration') }}">Teams & accounts</a><a href="{{ route('erp.operations') }}">Operations & audit</a>@endif
        <a href="{{ route('erp.index') }}" @if($isHome) aria-current="page" @endif>@include('erp.icon',['name'=>'grid'])<span>Business units</span><span class="nav-count">{{ $navigationUnits->count() }}</span></a>
        <a href="{{ route('erp.tasks.mine') }}" @if(request()->routeIs('erp.tasks.*')) aria-current="page" @endif>@include('erp.icon',['name'=>'check'])<span>My assigned tasks</span></a>
        @if(request()->routeIs('erp.teacher.*'))<a href="{{ route('erp.teacher.index') }}" aria-current="page">@include('erp.icon',['name'=>'calendar'])<span>My lessons</span></a>@endif
    </nav>
    @if($toolScreen)
        @include('erp.tool-navigation')
    @elseif($currentUnit)
        <div class="sidebar-section-label">BUSINESS UNIT</div>
        <details class="unit-switcher"><summary><span class="unit-avatar small">{{ $currentUnit->code }}</span><span>{{ $currentUnit->name }}</span>@include('erp.icon',['name'=>'chevron'])</summary><div class="unit-switcher-options">@foreach($navigationUnits as $navigationUnit)<a href="{{ route('erp.business',[$navigationUnit->id,(new \App\Modules\Erp\Services\TeamPermissions())->landing(auth()->user(),$navigationUnit->id)]) }}">{{ $navigationUnit->name }}</a>@endforeach</div></details>
        <nav class="sidebar-nav" aria-label="Business workflows">@foreach($navigationPages as $key=>$item)<a href="{{ route('erp.business',[$currentUnit->id,$key]) }}" @if($currentPage===$key) aria-current="page" @endif>@include('erp.icon',['name'=>$item[1]])<span>{{ $item[0] }}</span></a>@endforeach</nav>
    @else
        @if($navigationUnits->isNotEmpty())<div class="sidebar-section-label">YOUR UNITS</div><nav class="sidebar-nav unit-nav" aria-label="Your business units">@foreach($navigationUnits as $navigationUnit)<a href="{{ route('erp.business',[$navigationUnit->id,(new \App\Modules\Erp\Services\TeamPermissions())->landing(auth()->user(),$navigationUnit->id)]) }}"><span class="unit-dot">{{ $navigationUnit->code }}</span><span>{{ $navigationUnit->name }}</span>@include('erp.icon',['name'=>'chevron'])</a>@endforeach</nav>@endif
    @endif
    <div class="sidebar-bottom"><a class="legacy-link" href="{{ route('teacher.portal') }}">@include('erp.icon',['name'=>'calendar'])<span>Teacher workspace</span></a>
        <a class="legacy-link" href="{{ route('tools.index') }}">@include('erp.icon',['name'=>'external'])<span>Records & administration</span></a>
        <div class="account-block"><span class="user-avatar">{{ $userInitials }}</span><span class="account-name">{{ $displayName }}<small>Signed in to ClapsLearn</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Sign out" title="Sign out">@include('erp.icon',['name'=>'logout'])</button></form></div>
    </div>
</aside>
<div class="app-body">
    <header class="app-topbar"><div class="breadcrumb"><button class="menu-toggle icon-button" type="button" aria-label="Open navigation" aria-controls="app-navigation" aria-expanded="false">@include('erp.icon',['name'=>'menu'])</button><a href="{{ route('erp.index') }}">Workspace</a>@include('erp.icon',['name'=>'chevron'])<span>{{ $toolTitle ?? $currentUnit->name ?? $workspaceLabel }}</span></div><div class="topbar-date">@include('erp.icon',['name'=>'calendar']){{ now($currentUnit->timezone ?? 'Asia/Kolkata')->format('D, d M Y') }}</div></header>
    <main id="main-content" class="app-main {{ $isHome ? 'workspace-home' : '' }}" tabindex="-1">
        @if(config('sheet_transition.authority')==='sheets')<p class="error" role="status">Sheets are the temporary source of truth. Make operational changes in the original Sheets. @if(config('sheet_transition.enabled'))<a href="{{ route('erp.sheets') }}">View imported records and refresh status</a>.@endif Existing ERP workflow screens show ERP records, which may differ from the Sheet copies.</p>@endif
        @if(session('status'))<p class="success" role="status">@include('erp.icon',['name'=>'check']){{ session('status') }}</p>@endif
        @if($errors->any())<div class="error" id="error-summary" tabindex="-1" aria-labelledby="error-heading"><h2 id="error-heading">There is a problem</h2><p>Your changes were not saved. Review the details below and try again.</p><ul>@foreach($errors->messages() as $field=>$messages)@foreach($messages as $message)<li data-error-field="{{ $field }}">{{ $message }}</li>@endforeach @endforeach</ul></div>@endif
        @if($toolScreen)<div class="page-heading"><div><div class="eyebrow">{{ $toolGroup }}</div><h1>{{ $toolTitle }}</h1><p>{{ $toolDescription }}</p></div><a class="button button-secondary" href="{{ route('tools.index') }}">All tools</a></div>@endif
        @yield('content')
        <footer class="page-footer"><span>ClapsLearn Operations</span><span>Built around better learning.</span></footer>
    </main>
    @include('erp.mobile-navigation')
</div>
@if($toolScreen)
@include('admin.layouts.footer')
@else
@include('erp.workflow-script')
@endif
@yield('scripts')
@stack('scripts')
</body></html>
