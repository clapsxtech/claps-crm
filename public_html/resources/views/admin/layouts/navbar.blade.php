@include('admin.layouts.head')
<body class="hold-transition sidebar-mini layout-fixed app-shell">
    <div class="wrapper">
      <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle navigation"><i class="fas fa-bars"></i></a>
          </li>
          <li class="nav-item d-none d-sm-inline-block">
            <a href="{{ route('erp.index') }}" class="nav-link app-page-link"><i class="fas fa-home mr-2"></i>Workspace</a>
          </li>
        </ul>
        <ul class="navbar-nav ml-auto">
          <li class="dropdown user user-menu nav-item">
            <a href="#" class="nav-link dropdown-toggle user-trigger" data-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
              <span class="user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
              <span class="d-none d-sm-inline">{{Auth::user()->name}}</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right account-menu">
              <div class="account-summary">
                <span class="user-avatar user-avatar-lg">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                <div><strong>{{ Auth::user()->name }}</strong><small>Member since {{ date('M Y', strtotime(Auth::user()->created_at)) }}</small></div>
              </div>
              <div class="dropdown-divider"></div>
              <button type="button" data-href="{{route('profile')}}" class="dropdown-item global-modal-trigger"><i class="fas fa-user-edit"></i> Edit profile</button>
              <a href="#" class="dropdown-item text-danger" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fas fa-sign-out-alt"></i> Sign out</a>
            </div>
          </li>
        </ul>
      </nav>
