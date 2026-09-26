@include('admin.layouts.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-md-4">
                    <div class="filter-box">
                        <label class="control-label">Students</label>
                        <select class="form-control select2 filter" id="student_name">
                            <option value="all">All</option>
                            @foreach ($students as $value)
                                <option data-teacher-name="{{ $value->student_name }}">{{ $value->student_name }}
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
                                                <th>Teacher Name</th>
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
                                                <th>Teacher Name</th>
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
                                                <th>Teacher Name</th>
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
                                                <th>Teacher Name</th>
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
                                                <th>Teacher Name</th>
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
                                                <th>Teacher Name</th>
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
                                                <th>Teacher Name</th>
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
@include('admin.layouts.footer')
<script>
    $(document).ready(function() {
        $('.btn-sync').on('click', function() {
            var url = '{{ route('syncTimeTableData') }}';

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
                        $('#lastSynced').text('Last Synced: ' + response.last_synced);
                    }

                    if (response.last_synced) {
                        reloadTableData(response.last_synced);
                    }
                },
                error: function(xhr, status, error) {
                    console.error(error);
                }
            });
        });
        $('.filter').on('change', function() {
            var selectedTeacher = $('#student_name').val();
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
                    if ("{{ $timeTable->student_name }}" === selectedTeacher) {
                        $('#monday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->monday_time }}</td>
                    <td>{{ $timeTable->monday_teacher }}</td>
                    <td>{{ $timeTable->monday_subject }}</td>
                    <td>{{ $timeTable->monday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->monday_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->monday_number }}</a>
                    </td>
                </tr>
            `);
                    }
                @endif
            @endforeach
            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->tuesday_time))
                    if ("{{ $timeTable->student_name }}" === selectedTeacher) {
                        $('#tuesday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->tuesday_time }}</td>
                    <td>{{ $timeTable->tuesday_teacher }}</td>
                    <td>{{ $timeTable->tuesday_subject }}</td>
                    <td>{{ $timeTable->tuesday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->tuesday_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->tuesday_number }}</a>
                    </td>


                </tr>
            `);
                    }
                @endif
            @endforeach

            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->wednesday_time))
                    if ("{{ $timeTable->student_name }}" === selectedTeacher) {
                        $('#wednesday-timetable tbody').append(`
                    <tr>
                    <td>{{ $timeTable->wednesday_time }}</td>
                    <td>{{ $timeTable->wednesday_teacher }}</td>
                    <td>{{ $timeTable->wednesday_subject }}</td>
                    <td>{{ $timeTable->wednesday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->wednesday_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->wednesday_number }}</a>
                    </td>

                </tr>
            `);
                    }
                @endif
            @endforeach
            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->thursday_time))
                    if ("{{ $timeTable->student_name }}" === selectedTeacher) {
                        $('#thursday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->thursday_time }}</td>
                    <td>{{ $timeTable->thursday_teacher }}</td>
                    <td>{{ $timeTable->thursday_subject }}</td>
                    <td>{{ $timeTable->thursday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->thursday_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->thursday_number }}</a>
                    </td>

                </tr>
            `);
                    }
                @endif
            @endforeach
            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->friday_time))
                    if ("{{ $timeTable->student_name }}" === selectedTeacher) {
                        $('#friday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->friday_time }}</td>
                    <td>{{ $timeTable->friday_teacher }}</td>
                    <td>{{ $timeTable->friday_subject }}</td>
                    <td>{{ $timeTable->friday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->friday_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->friday_number }}</a>
                    </td>

                </tr>
            `);
                    }
                @endif
            @endforeach
            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->saturday_time))
                    if ("{{ $timeTable->student_name }}" === selectedTeacher) {
                        $('#saturday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->saturday_time }}</td>
                    <td>{{ $timeTable->saturday_teacher }}</td>
                    <td>{{ $timeTable->saturday_subject }}</td>
                    <td>{{ $timeTable->saturday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->saturday_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->saturday_number }}</a>
                    </td>

                </tr>
            `);
                    }
                @endif
            @endforeach
            @foreach ($timeTables as $timeTable)
                @if (!empty($timeTable->sunday_time))
                    if ("{{ $timeTable->student_name }}" === selectedTeacher) {
                        $('#sunday-timetable tbody').append(`
                <tr>
                    <td>{{ $timeTable->sunday_time }}</td>
                    <td>{{ $timeTable->sunday_teacher }}</td>
                    <td>{{ $timeTable->sunday_subject }}</td>
                    <td>{{ $timeTable->sunday_hour }}</td>
                    <td><a href="{{ str_starts_with($timeTable->link, 'http://') || str_starts_with($timeTable->link, 'https://') ? $timeTable->link : 'https://' . $timeTable->link }}" target="_blank" class="subject-link">click here</a></td>
                    <td>
                    <a href="https://api.whatsapp.com/send?phone={{ $timeTable->sunday_number }}&text=Hi%20there" target="_blank" class="subject-link">{{ $timeTable->sunday_number }}</a>
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
