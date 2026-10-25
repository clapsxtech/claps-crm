@extends('erp.layout')
@section('content')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-md-4">
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
                <div class="col-sm-8">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="">{{ __('common.home') }}</a></li>
                        <li class="breadcrumb-item active">Time Table</li>
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
                                <h3 class="card-title" style="margin: 0;">Time Table</h3>
                                <p id="lastSynced" style="margin: 0;"></p>
                                <button type="button" class="btn btn-primary btn-sync">
                                    <i class="fa fa-plus-circle"></i> Sync Data
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <h5 style="text-align: center; font-weight: bold;color: green;">Monday</h5>
                                <div class="table-responsive">
                                    <table class="data-table table table-bordered table-striped mg-b-0"
                                        id="monday-timetable">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Student Name</th>
                                                <th>Subject</th>
                                                <th>Duration</th>
                                                <th>G-Meet</th>
                                                <th>Number</th>
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
                                    <table class="data-table table table-bordered table-striped mg-b-0"
                                        id="tuesday-timetable">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Student Name</th>
                                                <th>Subject</th>
                                                <th>Duration</th>
                                                <th>G-Meet</th>
                                                <th>Number</th>
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
                                    <table class="data-table table table-bordered table-striped mg-b-0"
                                        id="wednesday-timetable">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Student Name</th>
                                                <th>Subject</th>
                                                <th>Duration</th>
                                                <th>G-Meet</th>
                                                <th>Number</th>
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
                                    <table class="data-table table table-bordered table-striped mg-b-0"
                                        id="thursday-timetable">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Student Name</th>
                                                <th>Subject</th>
                                                <th>Duration</th>
                                                <th>G-Meet</th>
                                                <th>Number</th>
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
                                    <table class="data-table table table-bordered table-striped mg-b-0"
                                        id="friday-timetable">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Student Name</th>
                                                <th>Subject</th>
                                                <th>Duration</th>
                                                <th>G-Meet</th>
                                                <th>Number</th>
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
                                    <table class="data-table table table-bordered table-striped mg-b-0"
                                        id="saturday-timetable">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Student Name</th>
                                                <th>Subject</th>
                                                <th>Duration</th>
                                                <th>G-Meet</th>
                                                <th>Number</th>
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
                                    <table class="data-table table table-bordered table-striped mg-b-0"
                                        id="sunday-timetable">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Student Name</th>
                                                <th>Subject</th>
                                                <th>Duration</th>
                                                <th>G-Meet</th>
                                                <th>Number</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        </tbody>
                                    </table>
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
<script>
    $(document).ready(function() {
        $('.btn-sync').on('click', function() {
            var url = '{{ route('syncTimeTableData') }}';

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

                    if (response.last_synced) {
                        reloadTableData(response.last_synced);
                    }
                },
                error: function(xhr, status, error) {
                    alert((xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Unable to synchronize. Existing data was not changed.');
                }
            });
        });
        $('.filter').on('change', function() {
            var selectedTeacher = $('#teacher_name').val();
            reloadTableData(selectedTeacher);

        });

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
                    <td>{{ $timeTable->student_name }}</td>
                    <td>{{ $timeTable->monday_subject }}</td>
                    <td>{{ $timeTable->monday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->student_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->student_number }}</a>
                    </td>
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
                    <td>{{ $timeTable->student_name }}</td>
                    <td>{{ $timeTable->tuesday_subject }}</td>
                    <td>{{ $timeTable->tuesday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->student_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->student_number }}</a>
                    </td>


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
                    <td>{{ $timeTable->student_name }}</td>
                    <td>{{ $timeTable->wednesday_subject }}</td>
                    <td>{{ $timeTable->wednesday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->student_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->student_number }}</a>
                    </td>

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
                    <td>{{ $timeTable->student_name }}</td>
                    <td>{{ $timeTable->thursday_subject }}</td>
                    <td>{{ $timeTable->thursday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->student_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->student_number }}</a>
                    </td>

                </tr>
            `);
                    }
                @endif
            @endforeach
            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->friday_time))
                    if ("{{ $timeTable->friday_teacher }}" === selectedTeacher) {
                        $('#friday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->friday_time }}</td>
                    <td>{{ $timeTable->student_name }}</td>
                    <td>{{ $timeTable->friday_subject }}</td>
                    <td>{{ $timeTable->friday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->student_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->student_number }}</a>
                    </td>

                </tr>
            `);
                    }
                @endif
            @endforeach
            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->saturday_time))
                    if ("{{ $timeTable->saturday_teacher }}" === selectedTeacher) {
                        $('#saturday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->saturday_time }}</td>
                    <td>{{ $timeTable->student_name }}</td>
                    <td>{{ $timeTable->saturday_subject }}</td>
                    <td>{{ $timeTable->saturday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->student_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->student_number }}</a>
                    </td>

                </tr>
            `);
                    }
                @endif
            @endforeach
            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->sunday_time))
                    if ("{{ $timeTable->sunday_teacher }}" === selectedTeacher) {
                        $('#sunday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->sunday_time }}</td>
                    <td>{{ $timeTable->student_name }}</td>
                    <td>{{ $timeTable->sunday_subject }}</td>
                    <td>{{ $timeTable->sunday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->student_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->student_number }}</a>
                    </td>
                </tr>
            `);
                    }
                @endif
            @endforeach
        }
    });
    $("#bankingNav").addClass('active');
    $("#BankServiceCommissionGroupsNav").addClass('active');
</script>
@include('admin.layouts.scripts')

@endsection
