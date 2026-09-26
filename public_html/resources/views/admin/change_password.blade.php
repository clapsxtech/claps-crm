@include('admin.layouts.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h4>CHANGE PASSWORD</h4>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="{{url('admin')}}">HOME</a></li>
              <li class="breadcrumb-item active">CHANGE PASSWORD</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      @if (Session::has('message'))
            <div class="alert alert-{{ Session::get('status') }}" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                {{ Session::get('message') }}
            </div>
        @endif
        @if ($errors->any())
            @foreach ($errors->all() as $error)
            <div class="alert alert-danger" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
                {{ $error }}
            </div>
            @endforeach
        @endif
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
                <!-- general form elements -->
                <div class="card card-primary">
                  <div class="card-header">
                    <h3 class="card-title">CHANGE PASSWORD</h3>
                  </div>
                  <!-- /.card-header -->
                  <!-- form start -->
                  <form method="post" action="{{route('change_password')}}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">PASSWORD</label>
                                    <input type="text" class="form-control" placeholder="Password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group">
                                  <label for="password">CONFIRM PASSWORD</label>
                                  <input type="password" class="form-control" placeholder="Confirm Password" name="password_confirmation" required>
                              </div>
                          </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
    
                    <div class="card-footer">
                      <button type="submit" class="btn btn-success pull-right">UPDATE PASSWORD</button>
                    </div>
                  </form>
                </div>
          </div>
        </div>
      </div>
    </section>
</div>
@include('admin.layouts.footer')