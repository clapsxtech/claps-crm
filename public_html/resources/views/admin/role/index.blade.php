@include('admin.layouts.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            {{-- @if (auth()->user()->can('roles')) --}}
                <a class="btn btn-primary" href="{{route('roles.create')}}"><i class="fa fa-plus"></i> ADD</a>
            {{-- @endif --}}
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{url('admin')}}">HOME</a></li>
                <li class="breadcrumb-item active">ROLES</li>
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
            @if(Session::has('message'))
                <div class="alert alert-{{Session::get('status')}}" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    {{Session::get('message')}}
                </div>  
            @endif
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">ROLES</h3>
                </div>
                <div class="card-body">
                    <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4">
                        <div class="row">
                            <div class="col-sm-12">
                                <table class="table table-bordered table-striped data-table" id="roles_table">
                                    <thead>
                                        <tr>
                                            <th>ROLES</th>
                                            <th>ACTION</th>
                                        </tr>
                                    </thead>
                                </table>
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
{{-- delete modal start here--}}
<div class="modal fade" tabindex="-1" role="dialog" id="removeModal">
  <div class="modal-dialog" role="document">
      <div class="modal-content">
          <div class="modal-header">
              <h4 class="modal-title">REMOVE</h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              
          </div>

          <form role="form" action="{{url('admin/roles')}}" method="post" id="removeForm">
              <div class="modal-body">
                  <p>This will be deleted! Are you sure?</p>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-warning" data-dismiss="modal">CLOSE</button>
                  <button type="submit" class="btn btn-danger delete_button">DELETE</button>
              </div>
          </form>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{-- delete modal end here --}}
<div class="modal fade modal_class" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
@include('admin.layouts.footer')
<script >
    $(document).ready( function(){
        var roles_table = $('#roles_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{route('roles.index')}}",
            buttons:[],
            columnDefs: [ {
                "targets": 1,
                "orderable": false,
                "searchable": false
            } ]
        });
    });
    $(document).unbind('submit').on('submit', 'form#createForm', function(e){
        e.preventDefault();
        $(this).find('button[type="submit"]').attr('disabled', true);
        var data = $(this).serialize();
        $.ajax({
            method: "post",
            url: $(this).attr("action"),
            dataType: "json",
            data: data,
            success:function(result){
                if(result.success == true){
                    $('#createModal').modal('hide');
                    toastr.success(result.msg);
                    $('.data-table').DataTable().ajax.reload();
                }else{
                    toastr.error(result.msg);
                }
            }
        });
    });
       function remove(id){
           $('.delete_button').removeAttr('disabled');
           $(document).unbind('submit').on('submit', 'form#removeForm', function(e){
               e.preventDefault();
               $(this).find('button[type="submit"]').attr('disabled', true);
               $.ajax({
                   method: "delete",
                   url: $(this).attr("action")+'/'+id,
                   dataType: "json",
                   data: {
                       "_token": "{{ csrf_token() }}",
                       "id":id,
                   },
                   success:function(result){
                       if(result.success == true){
                           $('#removeModal').modal('hide');
                           toastr.success(result.msg);
                           $('.data-table').DataTable().ajax.reload();
                       }else{
                           $('#removeModal').modal('hide');
                           toastr.error(result.msg);
                           $('.data-table').DataTable().ajax.reload();
   
                       }
                   }
               });
           });
       }

    $(document).unbind('submit').on('submit', 'form#createForm', function(e){
        e.preventDefault();
        $(this).find('button[type="submit"]').attr('disabled', true);
        var data = $(this).serialize();
        $.ajax({
            method: "post",
            url: $(this).attr("action"),
            dataType: "json",
            data: data,
            success:function(result){
                if(result.success == true){
                    $('div.modal_class').modal('hide');
                    toastr.success(result.msg);
                    $('.data-table').DataTable().ajax.reload();
                }else{
                    toastr.error(result.msg);
                }
            }
        });
    });

    $(document).on('click', 'button.edit_button', function() {
        $('div.modal_class').load($(this).data('href'), function() {
            $(this).modal('show');
            $('form#edit_form').submit(function(e) {
                $(this).find('button[type="submit"]').attr('disabled', true);
                e.preventDefault();
                var data = $(this).serialize();

                $.ajax({
                    method: 'POST',
                    url: $(this).attr('action'),
                    dataType: 'json',
                    data: data,
                    success: function(result) {
                        if (result.success === true) {
                            $('div.modal_class').modal('hide');
                            toastr.success(result.msg);
                            $('.data-table').DataTable().ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            });
        });
    });
    $(document).on('click', '.btn-modal', function(e) {
        e.preventDefault();
        var container = $(this).data('container');
        $.ajax({
            url: $(this).data('href'),
            dataType: 'html',
            success: function(result) {
                $(container)
                    .html(result)
                    .modal('show');
            },
        });
    });
    $("#mastersNav").addClass('active');
    $("#jobtypesNav").addClass('active');
   </script>
   @include('admin.layouts.scripts')