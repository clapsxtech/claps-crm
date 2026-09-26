@include('admin.layouts.sidebar')
<link rel="stylesheet" type="text/css" href="{{ asset('frontent_form/daterangepicker.css') }}">
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
          <div class="row mb-2">
              <div class="col-md-4">
                  <div class="filter-box">
                      <label class="control-label">Student Name</label>
                      <select class="form-control select2 filter" id="student_name">
                          <option value="all">All</option>
                          @foreach ($student_name as $value)
                              <option value="{{ $value->student_name }}" data-teacher-name="{{ $value->student_name }}">
                                  {{ $value->student_name }}
                              </option>
                          @endforeach
                      </select>
                  </div>
              </div>
               <div class="col-md-2">
                <div class="filter-box">
                    <label class="control-label">Section</label>
                    <select class="form-control select2 filter" id="section">
                        <option value="all">All</option>
                        @foreach ($section as $value)
                            <option value="{{ $value->section }}">
                                {{ $value->section }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
              <div class="col-md-2">
                  <div class="filter-box">
                      <label class="control-label">Status</label>
                      <select class="form-control select2 filter" id="status">
                          <option value="all">All</option>
                          @foreach ($status as $value)
                              <option value="{{ $value->current_status }}">
                                  {{ $value->current_status }}
                              </option>
                          @endforeach
                      </select>
                  </div>
              </div>
              <div class="col-md-2">
                <div class="filter-box">
                    <label class="control-label">Admin Staff</label>
                    <select class="form-control select2 filter" id="admin_staff">
                        <option value="all">All</option>
                        @foreach ($admin_staff as $value)
                            <option value="{{ $value->admin_staff }}">
                                {{ $value->admin_staff }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
              <div class="col-sm-2">
                  <ol class="breadcrumb float-sm-right">
                      <li class="breadcrumb-item"><a href="{{ url('admin/settings') }}">{{ __('common.home') }}</a></li>
                      <li class="breadcrumb-item active">Due Report</li>
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
                                <h3 class="card-title" style="margin: 0;">Due Report</h3>
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
                                                    <th>Student Name</th>
                                                    <th>Student Code</th>
                                                    <th>What's App Number</th>
                                                    <th>Status</th>
                                                    <th>Hour Rate</th>
                                                    <th>Monthly (TT)</th>
                                                    <th>Advance Expected</th>
                                                    <th>SECTION</th>
                                                    <th>Admin staff</th>
                                                    <th>Last Month Balance</th>
                                                    <th>Received</th>
                                                    <th>Total Advance</th>
                                                    <th>Hour Taken</th>
                                                    <th>C - Amount</th>
                                                    <th>Error</th>
                                                    <th>Current Balance</th>
                                                    <th>status</th>
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
@include('admin.layouts.scripts')
<script>
    $(document).ready(function() {
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            dom: "<'row'<'col-sm-2'l><'col-sm-7 text-center'B><'col-sm-3'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-6'i><'col-sm-6'p>>",
            buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
            ajax: {
                url: "{{ route('duereport.index') }}",
                data: function(d) {
                  d.student_name = $('#student_name').val();
                  d.status = $('#status').val();
                  d.admin_staff = $('#admin_staff').val();
                  d.section = $('#section').val();


                    d.search = $('input[type="search"]').val();
                }
            },
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'student_name',
                    name: 'student_name'
                },
                {
                    data: 'student_code',
                    name: 'student_code'
                },
                {
                    data: 'phone',
                    name: 'phone'
                },
                {
                    data: 'status',
                    name: 'status'
                },
                {
                    data: 'hour_rate',
                    name: 'hour_rate'
                },
                {
                    data: 'monthly',
                    name: 'monthly'
                },
                {
                    data: 'advance_expected',
                    name: 'advance_expected'
                },
                {
                    data: 'section',
                    name: 'section'
                },
                {
                    data: 'admin_staff',
                    name: 'admin_staff'
                },
                {
                    data: 'last_month_balance',
                    name: 'last_month_balance'
                },
                {
                    data: 'recived',
                    name: 'recived'
                },
                {
                    data: 'total_advance',
                    name: 'total_advance'
                },
                {
                    data: 'hour_taken',
                    name: 'hour_taken'
                },
                {
                    data: 'c_amount',
                    name: 'c_amount'
                },
                {
                    data: 'error',
                    name: 'error'
                },
                {
                    data: 'balance',
                    name: 'balance'
                },
                {
                    data: 'current_status',
                    name: 'current_status'
                }
            ],
            lengthMenu: [
                [10, 50, 100, -1],
                [10, 50, 100, "All"]
            ],
            pageLength: 50
        });
        $('#student_name, #status,#admin_staff,#section').on('change', function() {
        table.ajax.reload(); 
    });
        $('.btn-sync').on('click', function() {
            var url = '{{ route('syncDueReportData') }}';
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
                        $('#lastSynced').text('Manually Last Synced: ' + response
                            .last_synced);
                    }

                    table.ajax.reload();
                },
                error: function(xhr, status, error) {
                    console.error(error);
                    alert('An error occurred while syncing. Please try again.');
                }
            });
        });

        // Set active navigation items
        $("#bankingNav").addClass('active');
        $("#BankServiceCommissionGroupsNav").addClass('active');
    });
</script>
