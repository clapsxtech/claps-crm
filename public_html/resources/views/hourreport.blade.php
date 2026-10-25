@extends('erp.portal-layout')
@section('content')

    <section class="report-panel">
        <div class="container ">
            <div class="row">
                <!-- Filter Section -->
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <label for="teacher_name">Teacher</label><select class="form-control select2 filter" id="teacher_name" style="width:100%" required
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
                        <label for="dob">Teacher password (dd/mm/yyyy)</label><input class="form-control filter" required placeholder="dd/mm/yyyy" autocomplete="off"
                             name="dob" id="dob" type="password">

                    </div>
                </div>
                 <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <label for="daterange">Date range</label><input class="form-control filter" required
                            placeholder="Choose dates" autocomplete="off"
                            name="daterange" id="daterange" type="text">
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="filter-box">
                        <label for="student_name">Student</label><select class="form-control select2 filter"  id="student_name" style="width:100%" required
                            name="student_name">
                            <option value="all">All students</option>

                        </select>
                    </div>
                </div>
            </div>
            <button type="button" id="verify-attendance" class="btn btn-primary mb-3">View attendance</button>
            <!-- Table Section -->
            <div class="table-container">
              <span id="attendanceMessage" class="portal-status" role="status">Choose a teacher and enter your password to view attendance.</span>
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
                                <th id="hour-total" aria-live="polite"></th>
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

@endsection
@section('scripts')




<script src="{{ mix('js/report-dates.js') }}"></script>
<script>
$(function () {
    $('.select2').select2();
    var admin = @json(app(\App\Modules\IdentityAccess\Services\SiteAccessPolicy::class)->isAdministrator(auth()->user()));
    var verified = admin, generation = 0, lookup = null, reportRequest = null;
    var message = $('#attendanceMessage'), button = $('#verify-attendance');
    if (admin) { $('#dob').attr('placeholder', 'Administrator: optional'); message.text('Administrator access verified.'); }
    $('#daterange').daterangepicker({ autoUpdateInput: false, locale: {format: 'YYYY-MM-DD', cancelLabel:'Clear dates'} });
    $('#daterange').on('apply.daterangepicker', function(_, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
        table.ajax.reload();
    }).on('cancel.daterangepicker', function() { $(this).val(''); table.ajax.reload(); });
    function credentials() { return {teacher_name: $('#teacher_name').val(), dob: $('#dob').val(), _token: @json(csrf_token())}; }
    function empty(draw) { return {draw:draw, recordsTotal:0, recordsFiltered:0, data:[]}; }
    var table = $('.data-table').DataTable({
        processing: true, serverSide: true, pageLength: 25,
        ajax: function(data, callback) {
            if (reportRequest) reportRequest.abort();
            $('#hour-total').text('');
            if (!verified) { callback(empty(data.draw)); return; }
            var current = generation;
            var payload = $.extend({}, data, credentials(), {student_name: $('#student_name').val()});
            var dates = $('#daterange').val().split(' - ');
            if (dates.length === 2) { payload.start_date = dates[0]; payload.end_date = dates[1]; }
            reportRequest = $.ajax({url: @json(route($reportRoute ?? 'attendanceReport')), type:'POST', data:payload,
                success:function(response) {
                    if (current !== generation) return;
                    message.text(response.recordsFiltered ? 'Attendance loaded. Expand a row to see all lesson details on mobile.' : 'No attendance matches these filters.').show();
                    callback(response);
                    var hours = (response.data || []).reduce(function(total,row) { return total + (Number(row.hour_taken) || 0); }, 0);
                    $('#hour-total').text('Page total: ' + Number(hours.toFixed(2)) + ' h');
                },
                error:function(xhr,status) {
                    if (status === 'abort' || current !== generation) return;
                    message.text((xhr.responseJSON || {}).message || 'Unable to load attendance. Please retry.').show(); callback(empty(data.draw));
                }
            });
        },
        columns:[{data:'student_name'},{data:'date'},{data:'hour_taken'},{data:'subject'},{data:'topic'}]
    });
    function invalidate() {
        generation++; verified = false;
        if (lookup) lookup.abort();
        if (reportRequest) reportRequest.abort();
        button.prop('disabled',false);
        $('#student_name').empty().append(new Option('All assigned students', 'all')).trigger('change.select2');
        message.text('Select View attendance to verify and load records.').show();
        table.ajax.reload();
    }
    $('#teacher_name').on('change', invalidate);
    $('#dob').on('input', invalidate);
    button.on('click', function() {
        invalidate();
        if (!admin && (!$('#teacher_name').val() || !$('#dob').val())) {
            message.text('Choose a teacher and enter your password first.'); return;
        }
        var current = generation;
        button.prop('disabled',true); message.text('Verifying your details…');
        lookup = $.ajax({url:@json(route('get-students')),type:'POST',data:credentials(),
            success:function(response) {
                if (current !== generation) return;
                $.each(response.students || [], function(_, student) { $('#student_name').append(new Option(student.student_name, student.student_name)); });
                verified = true; table.ajax.reload();
            },
            error:function(xhr,status) { if (status !== 'abort' && current === generation) message.text((xhr.responseJSON || {}).message || 'Verification failed. Check your details and retry.').show(); },
            complete:function() { if (current === generation) button.prop('disabled',false); }
        });
    });
    $('#student_name').on('change', function() {table.ajax.reload();});
    $(window).on('pagehide', invalidate);
});
</script>

@endsection
