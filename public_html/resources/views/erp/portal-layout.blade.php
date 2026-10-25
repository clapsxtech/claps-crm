@php
$portalPages = [
    'timetableReport'=>['My timetable','calendar','View your lessons for the week after verifying your teacher details.'],
    'attendance'=>['Record attendance','check','Record a completed lesson, its topic and the time taught.'],
    'attendanceReport'=>['Attendance history','calendar','Verify your details, then filter the teaching hours recorded for your students.'],
    'resultform'=>['Record results','book','Save an assessment result for one of your assigned students.'],
    'markreport'=>['Assessment history','book','Find recorded marks and review your students’ assessment results.'],
    'student-attendance-report'=>['Student attendance','people','Review attendance by student and date range.'],
];
$portalRoute=request()->route()->getName();
if($portalRoute==='timetable') $portalRoute='timetableReport';
$portalPage=$portalPages[$portalRoute] ?? ['Teacher tools','book','Your teaching workspace.'];
@endphp
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="referrer" content="no-referrer"><meta name="theme-color" content="#117c66">
<title>{{ $portalPage[0] }} · ClapsLearn Operations</title>
@include('erp.tool-assets')
<link rel="stylesheet" href="{{ mix('css/erp.css') }}"><script src="{{ mix('js/erp-shell.js') }}" defer></script>
</head><body class="erp-app tool-screen teacher-portal">
<a class="skip-link" href="#main-content">Skip to main content</a><button class="nav-backdrop" type="button" aria-label="Close navigation" tabindex="-1" hidden></button>
<aside class="app-sidebar" id="app-navigation" aria-label="Main navigation">
<a class="brand" href="{{ route('timetableReport') }}"><span class="brand-symbol">@include('erp.icon',['name'=>'book'])</span><span>ClapsLearn<small>TEACHER WORKSPACE</small></span></a>
<div class="sidebar-section-label">YOUR TEACHING DAY</div>
<nav class="sidebar-nav" aria-label="Teacher tools">@foreach($portalPages as $key=>$page)<a href="{{ route($key) }}" @if($portalRoute===$key) aria-current="page" @endif>@include('erp.icon',['name'=>$page[1]])<span>{{ $page[0] }}</span></a>@endforeach</nav>
<div class="sidebar-bottom"><p class="portal-help">Your records stay protected by the existing sign-in and teacher verification.</p>@auth<a class="legacy-link" href="{{ route('erp.index') }}">@include('erp.icon',['name'=>'grid'])<span>Operations workspace</span></a><form method="post" action="{{ route('logout') }}">@csrf<button class="button button-secondary" type="submit">Sign out</button></form>@else<a class="button button-secondary" href="{{ route('login') }}">Sign in to view reports</a>@endauth</div>
</aside>
<div class="app-body"><header class="app-topbar"><div class="breadcrumb"><button class="menu-toggle icon-button" type="button" aria-label="Open navigation" aria-controls="app-navigation" aria-expanded="false">@include('erp.icon',['name'=>'menu'])</button><span>Teacher workspace</span>@include('erp.icon',['name'=>'chevron'])<span>{{ $portalPage[0] }}</span></div></header>
<main class="app-main" id="main-content" tabindex="-1"><div class="page-heading"><div><div class="eyebrow">YOUR TEACHING DAY</div><h1>{{ $portalPage[0] }}</h1><p>{{ $portalPage[2] }}</p></div></div>
@yield('content')
<footer class="page-footer"><span>ClapsLearn Operations</span><span>Built around better learning.</span></footer></main></div>
@include('admin.layouts.footer')
@yield('scripts')
</body></html>
