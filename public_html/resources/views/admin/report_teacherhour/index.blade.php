@include('admin.layouts.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
              {{-- <button type="button" class="btn btn-primary btn-modal" data-href="{{route('departments.create')}}" data-container=".modal_class">
                <i class="fa fa-plus-circle"></i> {{__('common.add')}}</button> --}}
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{url('admin/settings')}}">{{__('common.home')}}</a></li>
                <li class="breadcrumb-item active">Teachers</li>
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
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title" style="margin: 0;">Teachers Hour Report</h3>
                        <p id="lastSynced" style="margin: 0;"></p>
                        <button type="button" class="btn btn-primary btn-sync">
                            <i class="fa fa-plus-circle"></i> Sync Data
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4">
                        <div class="row">
                            <div class="col-sm-12 table-responsive">
                                <table class="data-table table table-bordered table-striped mg-b-0">
                                    <thead>
                                      <tr>
                                        <th>Id</th>
                                        <th>Teacher Name</th>
                                        <th>Teacher Code</th>
                                        <th>Student Name</th>
                                        <th>Student Code</th>
                                        <th>Subject</th>
                                        <th>Date</th>
                                        <th>Time Stamp</th>
                                        <th>Chapter</th>
                                        <th>Topic</th>
                                        <th>Hour Taken</th>
                                        <th>HOME WORK</th>
                                      </tr>
                                    </thead>
                                    <tbody></tbody>
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
<div class="modal fade modal_class" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
@include('admin.layouts.footer')
<script >
    $(document).ready(function() {
        var table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        dom: "<'row'<'col-sm-2'l><'col-sm-7 text-center'B><'col-sm-3'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-6'i><'col-sm-6'p>>",
        buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
        ajax: {
          url: "{{ route('workreport.index') }}",
          data: function (d) {
                d.search = $('input[type="search"]').val();
            }
        },
        columns: [{ data: 'DT_RowIndex',
                    'orderable': false,
                    'searchable': false
                },
            {data: 'teacher_name', name: 'teacher_name'},
            {data: 'teacher_code', name: 'teacher_code'},
            {data: 'student_name', name: 'student_name'},
            {data: 'student_code', name: 'student_code'},
            {data: 'subject',      name: 'subject'},
            {data: 'date',         name: 'date'},
            {data: 'date_submitted', name: 'date_submitted'},
            {data: 'chapter', name: 'chapter'},
            {data: 'topic', name: 'topic'},
            {data: 'hour_taken', name: 'hour_taken'},
            {data: 'homework_status', name: 'homework_status'},

        ],
        lengthMenu: [[10, 50, 100, -1], [10, 50, 100, "All"]],
        pageLength: 50
      });
      $('.btn-sync').on('click', function() {
    var url = '{{ route("syncTeacherReportData") }}';

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log(response);
            if (response.message) {
                alert(response.message);
            }

            if (response.last_synced) {
                // Update UI to show last synced timestamp
                $('#lastSynced').text('Last Synced: ' + response.last_synced);
            }

            table.ajax.reload();
        },
        error: function(xhr, status, error) {
            console.error(error);
        }
    });
    });
    });
    $("#bankingNav").addClass('active');
    $("#BankServiceCommissionGroupsNav").addClass('active');
   </script>
   @include('admin.layouts.scripts')