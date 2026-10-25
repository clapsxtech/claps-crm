@if($toolAdministrator)
@foreach($toolGroups as $group => $items)
<details class="tool-nav-group" @if($toolGroup === $group) open @endif><summary>{{ $group }}</summary>
<nav class="sidebar-nav" aria-label="{{ $group }}">
@foreach($items as $key => $item)
@if(\Illuminate\Support\Facades\Route::has($item[2]))
<a href="{{ route($item[2]) }}" @if($toolKey === $key) aria-current="page" @endif>@include('erp.icon',['name'=>$item[3]])<span>{{ $item[0] }}</span></a>
@endif
@endforeach
</nav></details>
@endforeach
@else
<div class="sidebar-section-label">MY REPORTS</div>
<nav class="sidebar-nav" aria-label="My reports">
@foreach(['markreport'=>'Assessment history','attendanceReport'=>'Attendance history','student-attendance-report'=>'Student attendance history'] as $reportRoute => $label)
<a href="{{ route($reportRoute) }}" @if(request()->routeIs($reportRoute)) aria-current="page" @endif>@include('erp.icon',['name'=>'book'])<span>{{ $label }}</span></a>
@endforeach
</nav>
@endif
