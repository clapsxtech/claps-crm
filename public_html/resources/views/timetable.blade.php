<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Table</title>
    <link rel="stylesheet" href="{{ asset('frontent_form/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/select2.min.css') }}">
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
        #success-message {
    color: white;
    text-align: center; /* Add text-align property */
}
    </style>
</head>

<body>
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
                        <input class="form-control filter" required placeholder="password(dd/mm/yyyy)"
                            autocomplete="off" name="dob" id="dob" type="text">

                    </div>
                </div>
                <div class="col-md-3 mb-4">
                <div id="success-message"></div></div>
            </div>
            <div class="table-container">
              <h5 style="text-align: center; font-weight: bold;color: green;">Monday</h5>
                <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0" id="monday-timetable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>G-Meet</th>
                                <th>Student Name</th>
                                <th>Hour</th>
                            </tr>
                        </thead>
                        <tbody>
                           
                        </tbody>
                    </table>
                </div>
            </div>
            <br>
            <div class="table-container">
              <h5 style="text-align: center; font-weight: bold;color: green;">Tuesday</h5>
                <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0" id="tuesday-timetable" >
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>G-Meet</th>
                                <th>Student Name</th>
                                <th>Hour</th>
                            </tr>
                        </thead>
                        <tbody>
                           
                        </tbody>
                    </table>
                </div>
            </div>
            <br>
            <div class="table-container">
              <h5 style="text-align: center; font-weight: bold;color: green;">Wednesday</h5>
                <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0" id="wednesday-timetable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>G-Meet</th>
                                <th>Student Name</th>
                                <th>Hour</th>
                            </tr>
                        </thead>
                        <tbody>
                           
                        </tbody>
                    </table>
                </div>
            </div><br>
            <div class="table-container">
              <h5 style="text-align: center; font-weight: bold;color: green;">Thursday</h5>
                <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0" id="thursday-timetable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>G-Meet</th>
                                <th>Student Name</th>
                                <th>Hour</th>
                            </tr>
                        </thead>
                        <tbody>
                           
                        </tbody>
                    </table>
                </div>
            </div><br>
            <div class="table-container">
              <h5 style="text-align: center; font-weight: bold;color: green;">Friday</h5>
                <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0" id="friday-timetable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>G-Meet</th>
                                <th>Student Name</th>
                                <th>Hour</th>
                            </tr>
                        </thead>
                        <tbody>
                           
                        </tbody>
                    </table>
                </div>
            </div><br>
            <div class="table-container">
              <h5 style="text-align: center; font-weight: bold;color: green;">Saturday</h5>
              <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0" id="saturday-timetable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>G-Meet</th>
                                <th>Student Name</th>
                                <th>Hour</th>
                            </tr>
                        </thead>
                        <tbody>
                           
                        </tbody>
                    </table>
                </div>
            </div><br>
            <div class="table-container">
              <h5 style="text-align: center; font-weight: bold;color: green;">Sunday</h5>
                <div class="table-responsive">
                    <table class="data-table table table-bordered table-striped mg-b-0" id="sunday-timetable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>G-Meet</th>
                                <th>Student Name</th>
                                <th>Hour</th>
                            </tr>
                        </thead>
                        <tbody>
                           
                        </tbody>
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
        function reloadTableData(selectedTeacher) {

        $('#monday-timetable tbody').empty();
        $('#tuesday-timetable tbody').empty();
        $('#wednesday-timetable tbody').empty();
        $('#thursday-timetable tbody').empty();
        $('#friday-timetable tbody').empty();
        $('#sunday-timetable tbody').empty();
        $('#saturday-timetable tbody').empty();

        @foreach ($timeTables as $timeTable)
            @if (!empty($timeTable->monday_time))
                if ("{{ $timeTable->monday_teacher }}" === selectedTeacher) {
                    $('#monday-timetable tbody').append(`
                        <tr>
                            <td>{{ $timeTable->monday_time }}</td>
                            <td><a href="{{ (str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://')) ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                            <td>{{ $timeTable->student_name }}</td>
                            <td>{{ $timeTable->monday_hour }}</td>
                        </tr>
                    `);
                }
            @endif
        @endforeach
        @foreach ($timeTables as $timeTable)
            @if (!empty($timeTable->tuesday_time))
                if ("{{ $timeTable->tuesday_teacher }}" === selectedTeacher) {
                    $('#tuesday-timetable tbody').append(`
                        <tr>
                            <td>{{ $timeTable->tuesday_time }}</td>
                            <td><a href="{{ (str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://')) ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                            <td>{{ $timeTable->student_name }}</td>
                            <td>{{ $timeTable->tuesday_hour }}</td>
                        </tr>
                    `);
                }
            @endif
        @endforeach

        @foreach ($timeTables as $timeTable)
            @if (!empty($timeTable->wednesday_time))
                if ("{{ $timeTable->wednesday_teacher }}" === selectedTeacher) {
                    $('#wednesday-timetable tbody').append(`
                        <tr>
                            <td>{{ $timeTable->wednesday_time }}</td>
                            <td><a href="{{ (str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://')) ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                            <td>{{ $timeTable->student_name }}</td>
                            <td>{{ $timeTable->wednesday_hour }}</td>
                        </tr>
                    `);
                }
            @endif
        @endforeach
       @foreach ($timeTables as $timeTable)
            @if (!empty($timeTable->thursday_time))
                if ("{{ $timeTable->thursday_teacher }}" === selectedTeacher) {
                    $('#thursday-timetable tbody').append(`
                        <tr>
                            <td>{{ $timeTable->thursday_time }}</td>
                            <td><a href="{{ (str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://')) ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                            <td>{{ $timeTable->student_name }}</td>
                            <td>{{ $timeTable->thursday_hour }}</td>
                        </tr>
                    `);
                }
            @endif
        @endforeach @foreach ($timeTables as $timeTable)
            // Check if the teacher's name matches the selected teacher and data for Tuesday exists
            @if (!empty($timeTable->friday_time))
                if ("{{ $timeTable->friday_teacher }}" === selectedTeacher) {
                    $('#friday-timetable tbody').append(`
                        <tr>
                            <td>{{ $timeTable->friday_time }}</td>
                            <td><a href="{{ (str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://')) ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                            <td>{{ $timeTable->student_name }}</td>
                            <td>{{ $timeTable->friday_hour }}</td>
                        </tr>
                    `);
                }
            @endif
        @endforeach @foreach ($timeTables as $timeTable)
            @if (!empty($timeTable->saturday_time))
                if ("{{ $timeTable->saturday_teacher }}" === selectedTeacher) {
                    $('#saturday-timetable tbody').append(`
                        <tr>
                            <td>{{ $timeTable->saturday_time }}</td>
                            <td><a href="{{ (str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://')) ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                            <td>{{ $timeTable->student_name }}</td>
                            <td>{{ $timeTable->saturday_hour }}</td>
                        </tr>
                    `);
                }
            @endif
        @endforeach @foreach ($timeTables as $timeTable)
            @if (!empty($timeTable->sunday_time))
                if ("{{ $timeTable->sunday_teacher }}" === selectedTeacher) {
                    $('#sunday-timetable tbody').append(`
                        <tr>
                            <td>{{ $timeTable->sunday_time }}</td>
                            <td><a href="{{ (str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://')) ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                            <td>{{ $timeTable->student_name }}</td>
                            <td>{{ $timeTable->sunday_hour }}</td>
                        </tr>
                    `);
                }
            @endif
        @endforeach
    }
    $('#teacher_name, #dob').on('change', function() {
    var teacher_name = $('#teacher_name').val();
    var dob = $('#dob').val();

    if (teacher_name && dob) {
        $.ajax({
            url: '{{ route("get-teacher-dob") }}', 
            type: 'GET',
            data: { teacher_name: teacher_name, dob: dob },
            success: function(response) {
                if (response.message === 'Teacher found') {
                    reloadTableData(teacher_name);
                    $('#success-message').text('verified.'); // Append success message
                } else {
                    $('#success-message').text(''); 
                    alert( 'you enterd wrong password')
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
    } else {
        $('#success-message').text(''); // Clear previous success message if no data is entered
    }
});


        $('.filter').on('change', function() {
            reloadTableData(teacher_name);
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
                if (inputValue.length === 10) {
                    $(this).change();
                }
            }
        } else {
            $(this).val(inputValue.replace(/[^\d\/]/g, ''));
        }
    }
});
});
</script>