@include('admin.layouts.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>SETTINGS</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item active">HOME</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
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
                <!-- general form elements -->
                <div class="card card-primary">
                  <div class="card-header">
                    <h3 class="card-title">UPDATE SETTINGS</h3>
                  </div>
                  <!-- /.card-header -->
                  <!-- form start -->
                  <form method="post" action="{{url('admin/update-settings')}}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="name">NAME</label>
                                    <input type="text" class="form-control" placeholder="Enter Name" name="name" value="{{old('name',$settings->name)}}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                              <div class="form-group">
                                  <label for="name">Address</label>
                                  <input type="text" class="form-control" placeholder="Enter Address" name="address" value="{{old('address',$settings->address)}}" required>
                              </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Phone</label>
                                    <input type="text" class="form-control" placeholder="Enter Quick Contact Number" name="phone" value="{{old('phone',$settings->phone)}}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group">
                                  <label for="name">Support Mail</label>
                                  <input type="text" class="form-control" placeholder="Enter Support Mail" name="support_mail" value="{{old('support_mail',$settings->support_mail)}}" required>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group">
                                  <label for="name">Admin Mail</label>
                                  <input type="text" class="form-control" placeholder="Enter Admin Mail" name="admin_mail" value="{{old('admin_mail',$settings->admin_mail)}}" required>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group">
                                  <label for="name">Career Mail</label>
                                  <input type="text" class="form-control" placeholder="Enter Career Mail" name="career_mail" value="{{old('career_mail',$settings->career_mail)}}" required>
                              </div>
                            </div>
                            <div class="col-md-12">
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                      <span class="input-group-text"><i class="fab fa-facebook"></i></span>
                                    </div>
                                    <input type="text" class="form-control" placeholder="Facebook" name="facebook" value="{{old('facebook',$settings->facebook)}}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                      <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                                    </div>
                                    <input type="text" class="form-control" placeholder="INSTAGRAM" name="instagram" value="{{old('instagram',$settings->instagram)}}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                      <span class="input-group-text"><i class="fab fa-youtube"></i></span>
                                    </div>
                                    <input type="text" class="form-control" placeholder="YOUTUBE CHANNEL" name="youtube" value="{{old('youtube',$settings->youtube)}}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                              <div class="input-group mb-3">
                                  <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-linkedin"></i></span>
                                  </div>
                                  <input type="text" class="form-control" placeholder="LINKED IN" name="linkedin" value="{{old('linkedin',$settings->linkedin)}}" required>
                              </div>
                            </div>
                            <div class="col-md-12">
                              <div class="input-group mb-3">
                                  <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-twitter"></i></span>
                                  </div>
                                  <input type="text" class="form-control" placeholder="TWITTER" name="twitter" value="{{old('twitter',$settings->twitter)}}" required>
                              </div>
                            </div>
                            {{-- <div class="col-md-6">
                                <div class="form-group">
                                    <label for="exampleInputFile">LOGO</label>
                                    <div class="input-group">
                                      <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="logo" name="logo">
                                        <label class="custom-file-label" for="exampleInputFile">Choose file</label>
                                      </div>
                                    </div>
                                  </div>
                            </div>
                            @isset($settings->logo)
                              <div class="col-md-6">
                                <div class="form-group">
                                    <img src="{{asset($settings->logo)}}" height="75px">
                                </div>
                              </div>
                            @endisset --}}
                            
                          <div class="col-md-12">
                            <label for="name">GOOGLE MAP</label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                  <span class="input-group-text"><i class="fa fa-map-marker"></i></span>
                                </div>
                                <input type="text" class="form-control" placeholder="GOOGLE MAP" name="map" value="{{old('map',$settings->map)}}">
                            </div>
                        </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
    
                    <div class="card-footer">
                      <button type="submit" class="btn btn-success pull-right">UPDATE SETTINGS</button>
                    </div>
                  </form>
                </div>
          </div>
        </div>
      </div>
    </section>
</div>
@include('admin.layouts.footer')
<script>
  $("#settingsNav").addClass('active');
</script>