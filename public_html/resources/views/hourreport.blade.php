<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report Form</title>
    <link rel="stylesheet" href="{{ asset('frontent_form/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontent_form/daterangepicker.css') }}">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(to bottom right, rgba(6, 52, 54, 1), rgba(6, 52, 54, 1));
            font-family: Arial, sans-serif;
        }

        .gradient-custom {
            background: #c6c8c8;
            background: -webkit-linear-gradient(to bottom right, rgba(6, 52, 54, 1), rgba(6, 52, 54, 1));
            background: linear-gradient(to bottom right, rgba(6, 52, 54, 1), rgba(6, 52, 54, 1));
        }

        .card-registration {
            border-radius: 15px;
        }

        .card-registration .card-body {
            padding: 4rem 2rem;
        }

        .select-input.form-control[readonly]:not([disabled]) {
            font-size: 1rem;
            line-height: 2.15;
            padding-left: .75em;
            padding-right: .75em;
        }

        .radio-inline {
            display: inline-block;
            margin-right: 10px;
        }

        .radio-label {
            cursor: pointer;
        }

        .select-arrow {
            top: 13px;
        }

        .modal-dialog.modal-dialog-centered {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100% - 1rem);
        }

        .table-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            margin-bottom: 0;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-top: 1px solid #dee2e6;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .data-table {
            width: 100%;
        }

        .data-table th,
        .data-table td {
            padding: 10px;
            text-align: center;
        }

        /* Style even rows differently for better readability */
        .data-table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        /* Style DataTable's search input */
        .dataTables_filter input {
            width: 200px;
            padding: 5px;
        }

        .dataTables_paginate {
            display: none;
        }
    </style>
</head>

<body class="public-page">
    <section class="h-100 gradient-custom">
        <div class="container py-5 h-100">
            <div class="row">
                <!-- Filter Section -->
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <select class="form-control select2 filter" id="teacher_name" style="width:100%" required
                            name="teacher_name">
                            <option value="">Select Teacher</option>
                            @foreach ($teachers as $value)
                                <option data-teacher-name="{{ $value->teacher_name }}">{{ $value->teacher_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <input class="form-control filter" required placeholder="password(dd/mm/yyyy)" autocomplete="off"
                             name="dob" id="dob" type="text">

                    </div>
                </div>
                 <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <input class="form-control filter" required
                            placeholder="Today" autocomplete="off"
                            name="daterange" id="daterange" type="text">
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <select class="form-control select2 filter"  id="student_name" style="width:100%" required
                            name="student_name">
                            <option value="all">All Student</option>

                        </select>
                    </div>
                </div>
            </div>
            <!-- Table Section -->
            <div class="table-container">
              <span id="attendanceMessage" style="color: red; display: show;">NB:-Fill data for attendance view</span>
                <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Date</th>
                                <th>Hour</th>
                                <th>Subject</th>
                                <th>Topic</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <th></th>
                                <th></th>
                                <th id="hour-total"></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <!-- ... (scripts and jQuery code) ... -->
</body>

</html>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="{{ asset('js/jquery.dataTables.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script>
$(function () {
    $('.select2').select2();
    var admin = @json(app(\App\Modules\IdentityAccess\Services\SiteAccessPolicy::class)->isAdministrator(auth()->user()));
    $('#dob').attr('type', 'password');
    if (admin) $('#dob').attr('placeholder', 'Administrator: optional');
    $('#daterange').daterangepicker({ autoUpdateInput: false, locale: {format: 'YYYY-MM-DD'} });
    $('#daterange').on('apply.daterangepicker', function(_, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
        table.ajax.reload();
    }).on('cancel.daterangepicker', function() { $(this).val(''); table.ajax.reload(); });
    function credentials() { return {teacher_name: $('#teacher_name').val(), dob: $('#dob').val(), _token: @json(csrf_token())}; }
    var table = $('.data-table').DataTable({
        processing: true, serverSide: true, pageLength: 25,
        ajax: function(data, callback) {
            if (!admin && (!$('#teacher_name').val() || !$('#dob').val())) {
                callback({draw:data.draw, recordsTotal:0, recordsFiltered:0, data:[]}); return;
            }
            var payload = $.extend({}, data, credentials(), {student_name: $('#student_name').val()});
            var dates = $('#daterange').val().split(' - ');
            if (dates.length === 2) { payload.start_date = dates[0]; payload.end_date = dates[1]; }
            $.ajax({url: @json(route($reportRoute ?? 'attendanceReport')), type:'POST', data:payload,
                success:function(response) { $('#attendanceMessage').hide(); callback(response); },
                error:function(xhr) { $('#attendanceMessage').text((xhr.responseJSON || {}).message || 'Unable to verify access.').show(); callback({draw:data.draw,recordsTotal:0,recordsFiltered:0,data:[]}); }
            });
        },
        columns:[{data:'student_name'},{data:'date'},{data:'hour_taken'},{data:'subject'},{data:'topic'}]
    });
    $('#teacher_name, #dob').on('change', function() {
        $('#student_name').empty().append(new Option('All assigned students', 'all'));
        if (!admin && (!$('#teacher_name').val() || !$('#dob').val())) { table.ajax.reload(); return; }
        $.ajax({url:@json(route('get-students')),type:'POST',data:credentials(),success:function(response) {
            $.each(response.students || [], function(_, student) { $('#student_name').append(new Option(student.student_name, student.student_name)); });
            table.ajax.reload();
        },error:function(xhr) { $('#attendanceMessage').text((xhr.responseJSON || {}).message || 'Verification failed.').show(); table.ajax.reload(); }});
    });
    $('#student_name').on('change', function() {table.ajax.reload();});
});
</script>
