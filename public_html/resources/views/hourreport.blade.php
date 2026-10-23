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
         $(document).ready(function() {
        $('.select2').select2();
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
                url: "{{ route('attendanceReport') }}",
                data: function(d) {
                    d.search = $('input[type="search"]').val();
                    d.teacher_name = $('#teacher_name').val();
                    d.dob = $('#dob').val();
                    d.student_name = $('#student_name').val();
                    d.start_date = $('#daterange').data('daterangepicker').startDate.format('YYYY/MM/DD');
                    d.end_date = $('#daterange').data('daterangepicker').endDate.format('YYYY/MM/DD');
                }
            },
            columns: [{
                    data: 'student_name',
                    name: 'student_name'
                },
                {
                    data: 'date',
                    name: 'date'
                },
                {
                    data: 'hour_taken',
                    name: 'hour_taken'
                },
                {
                    data: 'subject',
                    name: 'subject'
                },
                {
                    data: 'topic',
                    name: 'topic'
                },
            ],
             footerCallback: function(row, data, start, end, display) {
                var api = this.api();
                var data;
                var intVal = function(i) {
                    if (typeof i === 'string') {
                        i = i.replace(/[\a-zA-Z₹]/g, '') * 1;
                    }
                    if (Number.isNaN(i)) {
                        return 0;
                    }
                    return i;
                };

                var hour_taken = api
                    .column(2, {
                        page: 'current'
                    })
                    .data()
                    .reduce(function(a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);

                $(api.column(0).footer()).html('Total');
                $(api.column(2).footer()).html(hour_taken);
            },
             lengthMenu: [
                [10, 50, 100, -1],
                [10, 50, 100, "All"]
            ],
            pageLength: -1
        });
      
        $('#teacher_name, #dob, #fromdate, #todate').on('change', function() {
             $('#attendanceMessage').hide();
            var teacher_name = $('#teacher_name').val();
            var dob = $('#dob').val();
           
          if (teacher_name && dob) {
              $.ajax({
                  url: '{{ route("get-students") }}', 
                  type: 'GET',
                  data: { teacher_name: teacher_name,dob:dob },
                  success: function(response) {
                    $('#student_name').empty();
                      $('#student_name').append('<option value="all">Select Student</option>');
                      
                      $.each(response.students, function(key, value) {
                          $('#student_name').append('<option data-student-name="' + value.student_name + '">' + value.student_name + '</option>');
                      });
                  }
              });
          } else {
              $('#student_name').empty().append('<option value="all">Select Student</option>');
          }
          table.ajax.reload();
        });
     
        $('#teacher_name, #student_name').on('change', function() {
        table.ajax.reload();
    });
        $('#dob').on('input', function(e) {
       var inputValue = $(this).val();
       var key = e.originalEvent.data;

      if (key === null) {
         var newValue = inputValue.slice(0, -1);
         $(this).val(newValue);
     } else {
        if (/^[\d\/]$/.test(key)) { 
            if (/^\d$/.test(key)) {
                if (inputValue.length === 2 || inputValue.length === 5) { 
                    $(this).val(inputValue + '/');
                }
                if (inputValue.length > 10) {
                    $(this).val(inputValue.slice(0, 10));
                }
            }
        } else {
            $(this).val(inputValue.replace(/[^\d\/]/g, ''));
         }
     }
    });
    });
   
</script>
