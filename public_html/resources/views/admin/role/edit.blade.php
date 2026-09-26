@include('admin.layouts.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            {{-- @if (auth()->user()->can('roles')) --}}
                {{-- <a class="btn btn-primary" href="{{route('roles.create')}}"><i class="fa fa-plus"></i> ADD</a> --}}
            {{-- @endif --}}
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{url('admin')}}">HOME</a></li>
                <li class="breadcrumb-item"><a href="{{route('roles.index')}}">ROLES</a></li>
                <li class="breadcrumb-item active">EDIT</li>
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
            <!-- general form elements -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">ROLES</h3>
                </div>
                <div class="card-body">
                    <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4">
                        <div class="row">
                            <div class="col-sm-12">
                                <form method="POST" action="{{url('admin/roles').'/'.$role->id}}" accept-charset="UTF-8" id="role_edit_form">
                                    <input name="_method" type="hidden" value="PUT">
                                    @csrf 
                                    <div class="box-body">
                                    
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="name">ROLE NAME:*</label>
                                                    <input class="form-control" value="{{$role->name}}" required placeholder="Role Name" name="name" type="text" id="name" autocomplete="off">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <table class="table table-bordered table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>PERMISSION</th>
                                                            <th>CHECK</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if (!empty($permission))
                                                            @foreach ($permission as $v)
                                                                <tr>
                                                                    <td>{{$v['name']}}</td>
                                                                    <td><input class="input-icheck" name="permissions[]" type="checkbox" value="{{$v['name']}}" @if(in_array($v['name'], $role_permissions)) checked @endif></td>
                                                                        
                                                                </tr>
                                                            @endforeach
                                                        @endif
                                                        
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    
                                    </div>
                                    <!-- /.box-body -->
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-primary pull-right">SAVE</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
          </div>
        </div>
      </div>
    </section>
</div>
@include('admin.layouts.footer')
<script >
    $("#mastersNav").addClass('active');
    $("#RolesNav").addClass('active');
</script>