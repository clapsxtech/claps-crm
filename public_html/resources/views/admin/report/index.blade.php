@extends('erp.layout')
@section('content')
<link rel="stylesheet" type="text/css" href="{{ asset('frontent_form/daterangepicker.css') }}">
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-md-4">
            <div class="filter-box">
                <label class="control-label">Date</label>
                <input class="form-control filter" required
                    placeholder="ALL" autocomplete="off"
                    name="daterange" id="daterange" type="text">
            </div>
        </div>
          <div class="col-sm-8">
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
                    <h3 class="card-title" style="margin: 0;">Masters</h3>
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
                                        <th>Total Hour</th>
                                        <th>C-Fees</th>
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
@endsection
@section('scripts')
@include('admin.layouts.scripts')
<script >
    $(document).ready(function() {
      $('#daterange').daterangepicker({
            autoUpdateInput: false,
            locale: {
                format: 'DD/MM/YYYY'
            },
            opens: 'left',
            autoApply: true,
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });

        $('#daterange').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            table.ajax.reload();
        });

        $('#daterange').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
        });
        var table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        dom: "<'row'<'col-sm-2'l><'col-sm-7 text-center'B><'col-sm-3'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-6'i><'col-sm-6'p>>",
        buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"],
        ajax: {
          url: "{{ route('monthlyreport.index') }}",
          data: function (d) {
                d.search = $('input[type="search"]').val();
                var dateRange = $('#daterange').val();
                    if (dateRange) {
                        var dates = dateRange.split(' - ');
                        d.start_date = moment(dates[0], 'YYYY-MM-DD').format('YYYY/MM/DD');
                        d.end_date = moment(dates[1], 'YYYY-MM-DD').format('YYYY/MM/DD');
                    }
            }
        },
        columns: [{ data: 'DT_RowIndex',
                    'orderable': false,
                    'searchable': false
                },
            {data: 'student_name', name: 'student_name'},
            {data: 'student_code', name: 'student_code'},
            {data: 'hour_taken', name: 'hour_taken'},
            {data: 'total_amount',  name: 'total_amount'},
                    ],
        lengthMenu: [[10, 50, 100, -1], [10, 50, 100, "All"]],
        pageLength: 50
      });
   
    });
    $("#bankingNav").addClass('active');
    $("#BankServiceCommissionGroupsNav").addClass('active');
   </script>
   @include('admin.layouts.scripts')
@endsection
