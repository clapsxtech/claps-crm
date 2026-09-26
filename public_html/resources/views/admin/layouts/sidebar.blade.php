@include('admin.layouts.navbar')
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="{{url('')}}" class="brand-link text-center">
    <span class="brand-text font-weight-light">ADMIN</span>
  </a>
  <!-- Sidebar -->
  <div class="sidebar">
    <!-- Sidebar user (optional) -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <img src="{{asset('img/user2.png')}}" class="img-circle elevation-2" alt="User Image">
      </div>
      <div class="info">
      </div>
    </div>
    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        {{-- <li class="nav-item">
          <a href="{{url('admin/settings')}}" class="nav-link">
            <i class="nav-icon fa fa-cog"></i>
            <p>{{ __('sidebar.dashboard') }}</p>
          </a>
        </li> --}}
      <li class="nav-item">
        <ul class="nav nav-treeview" style="display: none;">
          {{-- <li class="nav-item"><a href="{{route('roles.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
              <p>ROLES</p>
            </a></li> --}}
            {{-- <li class="nav-item"><a href="{{route('permissions.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
              <p>PERMISSION</p>
            </a></li> --}}
        </ul>
      </li>`
     {{-- <li class="nav-item"><a href="{{route('teachers.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Teachers</p>
      </a></li> --}}
      {{-- <li class="nav-item"><a href="{{route('students.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Student</p>
      </a></li> --}}
      <li class="nav-item"><a href="{{route('marklist.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Mark List</p>
      </a>
      <li class="nav-item"><a href="{{route('subjects.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Subject</p>
      </a></li>
      <li class="nav-item"><a href="{{route('topics.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Topics</p>
      </a></li>
      <li class="nav-item"><a href="{{route('master.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Master</p>
      </a></li>
      
      <li class="nav-item"><a href="{{route('workhour.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Work Hour</p>
      </a></li>
       <li class="nav-item"><a href="{{route('duereport.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Due Report</p>
      </a></li>
      <li class="nav-item"><a href="{{route('timetable.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Timetable</p>
      </a></li>
      </li>
      <li class="nav-item"><a href="{{route('studentTimetable')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Student Timetable</p>
      </a></li>

      <li class="nav-item"><a href="{{route('marklistreport.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>Marklist Report</p>
      </a></li>
      <li class="nav-item"><a href="{{route('users.index')}}" class="nav-link"><i class="far fa-circle nav-icon"></i>
        <p>USERS</p>
      </a></li>
      <li class="nav-item">
        <a href="javascript:void" class="nav-link" onclick="$('#logout-form').submit();">
          <i class="nav-icon fas fa-sign-out-alt"></i>
          <p>LOGOUT</p>
        </a>
      </li>

      </ul>

      <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
      </form>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
