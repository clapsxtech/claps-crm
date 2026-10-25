@include('admin.layouts.sidebar')
<link rel="stylesheet" type="text/css" href="{{ asset('frontent_form/daterangepicker.css') }}">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <label class="control-label">Teachers</label>
                        <select class="form-control select2 filter" id="teacher_name">
                            <option value="all">All</option>
                            @foreach ($teachers as $value)
                                <option data-teacher-name="{{ $value->teacher_name }}">{{ $value->teacher_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <label class="control-label">Student</label>
                        <select class="form-control select2 filter" id="student_name" style="width:100%" required
                            name="student_name">
                            <option value="all">All</option>
                            @foreach ($students as $value)
                                <option data-student-name="{{ $value->student_name }}">{{ $value->student_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <label class="control-label">Date</label>
                        <input class="form-control filter" placeholder="ALL" autocomplete="off" name="daterange"
                            id="daterange" type="text">
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <label class="control-label">Status</label>
                        <select class="form-control select2 filter" id="status" style="width:100%" name="status">
                            <option value="all">All</option>
                            <option value="true">True</option>
                            <option value="false">Error</option>
                            </option>
                        </select>
                    </div>
                </div>
                
            </div>

        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
         <form method="GET" action="{{ route('work_hours.export') }}">
    <input type="date" name="start_date" value="{{ request('start_date') }}" />
    <input type="date" name="end_date" value="{{ request('end_date') }}" />
    <button 
        type="submit" 
        style="background-color: green; color: white; font-weight: bold; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
        Export Work Hours
    </button>
</form>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 class="card-title" style="margin: 0;">Work Hour Managment</h3>
                                <p id="lastSynced" style="margin: 0;"></p>
                                {{-- <button type="button" class="btn btn-primary btn-modal"
                                    data-href="{{ route('workhour.create') }}" data-container=".modal_class">
                                    <i class="fa fa-plus-circle"></i> {{ __('common.add') }}</button> --}}
                               
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="example2_wrapper" class="dataTables_wrapper dt-bootstrap4">
                                <div class="row">
                                    <div class="col-sm-12 table-responsive">
                                        <table class="data-table table table-bordered table-striped mg-b-0">
                                            <thead>
                                                <tr>
                                                    <th>Status</th>
                                                    <th>Team</th>
                                                    <th>Teacher Name </th>
                                                    <th>Teacher Code</th>
                                                    <th>Student Name </th>
                                                    <th>Student Code</th>
                                                    <th>Subject</th>
                                                    <th>Date</th>
                                                    <th>Time stamp</th>
                                                    <th>Chapter</th>
                                                    <th>Topic</th>
                                                    <th>Hour Taken</th>
                                                    <th>Home Work</th>
                                                    <th>Hour payment</th>
                                                    <th>Payment Per Hour</th>
                                                    <th>Profit Per Hour</th>
                                                    <th>Total payable</th>
                                                    <th>action</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="11" style="text-align:right">Total:</th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                </tr>
                                            </tfoot>
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
<script src="{{ asset('browser-vendor/ion.rangeSlider.min.js') }}"></script>
<script>
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
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1,
                    'month').endOf('month')]
            }
        });

        $('#daterange').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format(
                'YYYY-MM-DD'));
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
                url: "{{ route('workhour.index') }}",
                data: function(d) {
                    d.search = $('input[type="search"]').val();
                    if ($('#student_name').val()) {
                        d.student_name = $('#student_name').val();
                    }
                    if ($('#teacher_name').val()) {
                        d.teacher_name = $('#teacher_name').val();
                    }
                    if ($('#status').val()) {
                        d.status = $('#status').val();
                    }
                    if ($('#duplicate').val()) {
                        d.duplicate = $('#duplicate').val();
                    }
                    var dateRange = $('#daterange').val();
                    if (dateRange) {
                        var dates = dateRange.split(' - ');
                        d.start_date = moment(dates[0], 'YYYY-MM-DD').format('YYYY/MM/DD');
                        d.end_date = moment(dates[1], 'YYYY-MM-DD').format('YYYY/MM/DD');
                    }
                    d.columns = [{
                            data: 'student_name',
                            searchable: true
                        },
                        {
                            data: 'teacher_name',
                            searchable: true
                        },
                    ];
                }
            },
            columns: [{
                    data: 'status',
                    name: 'status',
                    render: function(data, type, row) {
                        if (data === 'true') {
                            return '<span class="badge badge-success">True</span>';
                        } else if (data === 'false') {
                            return '<span class="badge badge-danger">Error</span>';
                        } else {
                            return '';
                        }
                    }
                },
                {
                    data: 'team',
                    name: 'team'
                }, {

                    data: 'teacher_name',
                    name: 'teacher_name'
                },
                {
                    data: 'teacher_code',
                    name: 'teacher_code'
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
                    data: 'subject',
                    name: 'subject'
                },
                {
                    data: 'date',
                    name: 'date'
                },
                {
                    data: 'date_submitted',
                    name: 'date_submitted'
                },

                {
                    data: 'chapter',
                    name: 'chapter'
                },
                {
                    data: 'topic',
                    name: 'topic'
                },
                {
                    data: 'hour_taken',
                    name: 'hour_taken'
                },
                
                {
                    data: 'homework_status',
                    name: 'homework_status'
                },

                {
                    data: 'hour_payment',
                    name: 'hour_payment'
                },
                {
                    data: 'payment_per_hour',
                    name: 'payment_per_hour'
                },
                {
                    data: 'profit_per_hour',
                    name: 'profit_per_hour'
                },
                {
                    data: 'total_amount',
                    name: 'total_amount'
                },

                {
                    data: 'action',
                    name: 'action'
                },


            ],
            footerCallback: function(row, data, start, end, display) {
                var api = this.api();
                var intVal = function(i) {
                    return typeof i === 'string' ? i.replace(/[\$,]/g, '') * 1 : typeof i ===
                        'number' ? i : 0;
                };
                var totalHourTaken = api.column(11).data().reduce(function(a, b) {
                    return intVal(a) + intVal(b);
                }, 0);
                var totalHourPayment = api.column(13).data().reduce(function(a, b) {
                    return intVal(a) + intVal(b);
                }, 0);
                var totalPaymentPerHour = api.column(14).data().reduce(function(a, b) {
                    return intVal(a) + intVal(b);
                }, 0);
                var totalProfitPerHour = api.column(15).data().reduce(function(a, b) {
                    return intVal(a) + intVal(b);
                }, 0);
                var totalPayable = api.column(16).data().reduce(function(a, b) {
                    return intVal(a) + intVal(b);
                }, 0);

                $(api.column(11).footer()).html(totalHourTaken.toFixed(2));
                $(api.column(13).footer()).html(totalHourPayment.toFixed(2));
                $(api.column(14).footer()).html(totalPaymentPerHour.toFixed(2));
                $(api.column(15).footer()).html(totalProfitPerHour.toFixed(2));
                $(api.column(16).footer()).html(totalPayable.toFixed(2));
            },

            lengthMenu: [
                [10, 50, 100, -1],
                [10, 50, 100, "All"]
            ],
            pageLength: 50
        });

        $(document).ready(function() {
            $('#teacher_name').on('change', function() {
                var teacher_name = $(this).val();
                if (teacher_name) {
                    $.ajax({
                        url: '{{ route('get-student') }}',
                        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                        type: 'POST',
                        data: {
                            teacher_name: teacher_name
                        },
                        success: function(response) {
                            $('#student_name').empty().append(
                                '<option value="">All</option>');
                            $.each(response.students, function(key, value) {
                                $('#student_name').append(
                                    '<option data-student-name="' +
                                    value.student_name + '">' + value
                                    .student_name + '</option>');
                            });
                        }
                    });
                } else {
                    $('#student_name').empty().append('<option value="">All</option>');
                }
                table.ajax.reload();
            });

            $('#student_name').on('change', function() {
                var student_name = $(this).val();
                if (student_name) {
                    $.ajax({
                        url: '{{ route('get-teacher') }}',
                        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                        type: 'POST',
                        data: {
                            student_name: student_name
                        },
                        success: function(response) {
                            $('#teacher_name').empty().append(
                                '<option value="">All</option>');
                            $.each(response.teachers, function(key, value) {
                                $('#teacher_name').append(
                                    '<option data-teacher-name="' +
                                    value.teacher_name + '">' + value
                                    .teacher_name + '</option>');
                            });
                        }
                    });
                } else {
                    $('#teacher_name').empty().append('<option value="">All</option>');
                }
                table.ajax.reload();
            });

        });

        $('.filter').on('change', function() {
            table.ajax.reload();
        });


        $('.btn-sync').on('click', function() {
            var url = '{{ route('syncWorkHoureReportData') }}';

            $.ajax({
                url: url,
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    console.log(response);
                    if (response.message) {
                        alert(response.message);
                    }

                    if (response.last_synced) {
                        $('#lastSynced').text('Last Synced: ' + response.last_synced);
                    }

                    table.ajax.reload();
                },
                error: function(xhr, status, error) {
                    alert((xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Unable to synchronize. Existing data was not changed.');
                }
            });
        });

        $(document).unbind('submit').on('submit', 'form#createForm', function(e) {
            e.preventDefault();
            var data = $(this).serialize();
            $.ajax({
                method: "post",
                url: $(this).attr("action"),
                dataType: "json",
                data: data,
                success: function(result) {
                    if (result.success == true) {
                        $('div.modal_class').modal('hide');
                        toastr.success(result.msg);
                        $('.data-table').DataTable().ajax.reload();
                    } else {
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
                                $('.save_button').attr('disabled', false);
                            }
                        },
                    });
                });
            });
        });

    });
    $("#bankingNav").addClass('active');
    $("#BankServiceCommissionGroupsNav").addClass('active');
</script>
@include('admin.layouts.scripts')
