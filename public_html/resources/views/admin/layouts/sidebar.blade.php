@include('admin.layouts.navbar')
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="{{route('erp.index')}}" class="brand-link">
    <span class="brand-mark">C</span>
    <span class="brand-text">Clapslearn</span>
  </a>
  <div class="sidebar">
    <div class="sidebar-intro">
      <span>Legacy tools</span>
      <strong>Existing records</strong>
    </div>
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
      <li class="nav-item"><a href="{{ route('legacy.index') }}" class="nav-link"><i class="fas fa-arrow-left nav-icon"></i><p>Legacy tools directory</p></a></li>
      <li class="nav-header">ACADEMICS</li>
      <li class="nav-item"><a href="{{route('marklist.index')}}" class="nav-link"><i class="fas fa-clipboard-check nav-icon"></i>
        <p>Mark List</p>
      </a></li>
      <li class="nav-item"><a href="{{route('subjects.index')}}" class="nav-link"><i class="fas fa-book nav-icon"></i>
        <p>Subjects</p>
      </a></li>
      <li class="nav-item"><a href="{{route('topics.index')}}" class="nav-link"><i class="fas fa-layer-group nav-icon"></i>
        <p>Topics</p>
      </a></li>
      <li class="nav-item"><a href="{{route('master.index')}}" class="nav-link"><i class="fas fa-database nav-icon"></i>
        <p>Master Data</p>
      </a></li>
      <li class="nav-header">OPERATIONS</li>
      <li class="nav-item"><a href="{{route('erp.index')}}" class="nav-link"><i class="fas fa-building nav-icon"></i><p>ERP Workspace</p></a></li>
      <li class="nav-item"><a href="{{route('workhour.index')}}" class="nav-link"><i class="fas fa-clock nav-icon"></i>
        <p>Work Hours</p>
      </a></li>
       <li class="nav-item"><a href="{{route('duereport.index')}}" class="nav-link"><i class="fas fa-receipt nav-icon"></i>
        <p>Due Report</p>
      </a></li>
      <li class="nav-item"><a href="{{route('timetable.index')}}" class="nav-link"><i class="fas fa-calendar-alt nav-icon"></i>
        <p>Timetable</p>
      </a></li>
      <li class="nav-item"><a href="{{route('studentTimetable')}}" class="nav-link"><i class="fas fa-user-clock nav-icon"></i>
        <p>Student Timetable</p>
      </a></li>
      <li class="nav-header">REPORTS & ACCESS</li>
      <li class="nav-item"><a href="{{route('marklistreport.index')}}" class="nav-link"><i class="fas fa-chart-bar nav-icon"></i>
        <p>Marklist Report</p>
      </a></li>
      <li class="nav-item"><a href="{{route('users.index')}}" class="nav-link"><i class="fas fa-users nav-icon"></i>
        <p>Users</p>
      </a></li>
      <li class="nav-item">
        <button type="submit" form="logout-form" class="nav-link" style="border:0;background:transparent;width:100%;text-align:left;color:inherit">
          <i class="nav-icon fas fa-sign-out-alt"></i>
          <p>Sign out</p>
        </button>
      </li>

      </ul>
      <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
      </form>
    </nav>
  </div>
</aside>

