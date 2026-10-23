@include('admin.layouts.sidebar')

      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <section class="content-header">
          <div class="container-fluid">
            <div class="dashboard-hero">
              <div>
                <small class="text-uppercase">Clapslearn workspace</small>
                <h1>Welcome back, {{ Auth::user()->name }}</h1>
                <p>Manage academic records, attendance, schedules, and reports from one place.</p>
              </div>
              <a href="{{ route('marklist.create') }}" class="btn btn-light"><i class="fas fa-plus"></i> Add marks</a>
            </div>
          </div>
        </section>
        <section class="content">
          <div class="container-fluid">
            <div class="quick-grid" aria-label="Quick actions">
              <a class="quick-card" href="{{ route('workhour.index') }}"><i class="fas fa-clock"></i><div><strong>Work hours</strong><span>Review and manage attendance</span></div></a>
              <a class="quick-card" href="{{ route('marklist.index') }}"><i class="fas fa-clipboard-check"></i><div><strong>Mark list</strong><span>Record and review results</span></div></a>
              <a class="quick-card" href="{{ route('timetable.index') }}"><i class="fas fa-calendar-alt"></i><div><strong>Timetable</strong><span>Keep schedules organized</span></div></a>
              <a class="quick-card" href="{{ route('marklistreport.index') }}"><i class="fas fa-chart-bar"></i><div><strong>Reports</strong><span>Explore learning performance</span></div></a>
            </div>
          </div>
        </section>
      </div>
      @include('admin.layouts.footer')
