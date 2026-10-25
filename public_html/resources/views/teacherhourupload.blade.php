@extends('erp.portal-layout')
@section('content')
<section class="portal-form-panel"><div class="card"><div class="card-body">
<form action="{{ route('upload-workhour') }}" method="POST" id="markUploadForm">
@csrf<input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<h2 class="form-section-title">1. Teacher & student</h2><p class="text-muted">Select your name, enter your teacher password, then verify to load your students.</p>
<div class="row">
<div class="col-md-6 form-group"><label for="teacher_name">Teacher *</label><select class="form-control select2" id="teacher_name" name="teacher_name" required style="width:100%"><option value="">Select teacher</option>@foreach($teachers as $value)<option data-teacher-name="{{ $value->teacher_name }}">{{ $value->teacher_name }}</option>@endforeach</select></div>
<div class="col-md-6 form-group"><label for="password">Teacher password (dd/mm/yyyy) *</label><input class="form-control" id="password" name="password" type="password" required autocomplete="off" placeholder="dd/mm/yyyy"><button type="button" class="btn btn-outline-success mt-2" id="verify-teacher">Verify and load students</button><span id="verification-status" class="form-text text-muted" role="status"></span></div>
<div class="col-md-6 form-group"><label for="student_name">Student *</label><select class="form-control select2" id="student_name" name="student_name" required style="width:100%"><option value="">Verify your teacher details first</option></select></div>
<div class="col-md-3 form-group"><label for="teacher_code">Teacher code</label><input class="form-control" id="teacher_code" name="teacher_code" readonly></div>
<div class="col-md-3 form-group"><label for="student_code">Student code</label><input class="form-control" id="student_code" name="student_code" readonly></div>
</div>
<h2 class="form-section-title">2. Lesson details</h2><div class="row">
<div class="col-md-6 form-group"><label for="subject">Subject *</label><select class="form-control select2" id="subject" name="subject" required style="width:100%"><option value="">Select subject</option>@foreach($subject as $value)<option value="{{ $value->id }}">{{ $value->name }}</option>@endforeach</select></div>
<div class="col-md-6 form-group"><label for="date">Class date *</label><input class="form-control" id="date" name="date" type="date" required value="{{ old('date') }}" max="{{ date('Y-m-t') }}"></div>
<div class="col-md-6 form-group"><label for="chapter">Chapter *</label><input class="form-control" id="chapter" name="chapter" required placeholder="Chapter covered"></div>
<div class="col-md-6 form-group"><label for="topic">Topic *</label><input class="form-control" id="topic" name="topic" required placeholder="Topic taught"></div>
<div class="col-md-6 form-group"><label for="hour_taken">Lesson duration *</label><select class="form-control select2" id="hour_taken" name="hour_taken" required style="width:100%"><option value="">Choose duration</option><option value="0.5">30 minutes</option><option value="0.75">45 minutes</option><option value="1">1 hour</option><option value="1.25">1 hour 15 minutes</option><option value="1.5">1 hour 30 minutes</option><option value="2">2 hours</option></select></div>
<div class="col-md-6 form-group"><fieldset><legend class="field-label">Was homework assigned?</legend><label class="radio-inline"><input type="radio" id="homework-yes" aria-label="Yes" name="homework_status" value="yes"> Yes</label><label class="radio-inline"><input type="radio" id="homework-no" aria-label="No" name="homework_status" value="no"> No</label></fieldset></div>
</div>

<div id="error-alert" class="alert alert-danger" style="display:none" role="alert"></div>
<p class="text-muted">Rates and totals are calculated securely when you save.</p><button type="submit" class="btn btn-primary">Save attendance</button>
</form></div></div></section>
<div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title" id="successModalLabel">Attendance saved</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close">×</button></div><div class="modal-body">Your lesson has been recorded. You can review it in Attendance history.</div><div class="modal-footer"><button type="button" class="btn btn-primary" data-dismiss="modal">Done</button></div></div></div></div>
@endsection
@section('scripts')




<script>
$(function () {
    $('.select2').select2();
    var form = $('#markUploadForm');
    var pendingLookup = null;
    var saved = false;
    var saving = false;

    function showError(message) {
        $('#error-alert').text(message).show();
    }

    function clearAssignment() {
        if (pendingLookup) { pendingLookup.abort(); pendingLookup = null; }
        $('#student_name').empty().append(new Option('Verify your teacher identity first', '')).trigger('change');
        $('#student_code, #teacher_code').val('');
        $('#verification-status').text('Verify your teacher details to load students.');
    }

    $('#teacher_name, #password').on('change', clearAssignment);
    $('#password').on('input', clearAssignment);
    $('#student_name').on('change', function () {
        $('#student_code').val($(this).find(':selected').attr('data-student-code') || '');
    });

    $('#verify-teacher').on('click', function () {
        clearAssignment();
        var teacher = $('#teacher_name').val();
        var password = $('#password').val();
        if (!teacher || !password) {
            showError('Select a teacher and enter your verification code first.');
            return;
        }
        var button = $(this).prop('disabled', true);
        $('#error-alert').hide();
        $('#verification-status').text('Verifying…');
        pendingLookup = $.ajax({
            url: '{{ route("studentinfom") }}',
            type: 'POST',
            headers: { Accept: 'application/json' },
            data: { _token: form.find('[name="_token"]').val(), teacher_name: teacher, password: password },
            success: function (response) {
                $('#student_name').empty().append(new Option('Select Student', ''));
                $.each(response.students || [], function (_, student) {
                    var option = new Option(student.student_name, student.student_name);
                    $(option).attr('data-student-code', student.student_code);
                    $('#student_name').append(option);
                });
                $('#teacher_code').val(response.teacher_code);
                $('#verification-status').text((response.students || []).length ? 'Verified. Choose a student below.' : 'Verified, but no students are assigned.');
                $('#student_name').trigger('change');
                $('#error-alert').hide();
            },
            error: function (xhr, status) {
                if (status !== 'abort') {
                    $('#verification-status').text('Verification failed. Check your details and retry.');
                    showError((xhr.responseJSON || {}).msg || (xhr.responseJSON || {}).message || 'Teacher verification failed.');
                }
            },
            complete: function () { pendingLookup = null; button.prop('disabled', false); }
        });
    });

    form.on('submit', function (event) {
        event.preventDefault();
        if (saving || saved) return;
        saving = true;
        var submitButton = form.find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: '{{ route("upload-workhour") }}',
            type: 'POST',
            headers: { Accept: 'application/json' },
            data: form.serialize(),
            success: function (response) {
                if (!response.success) { showError(response.msg || 'Unable to save the record.'); return; }
                saved = true;
                form[0].reset();
                // Advance the key only after an acknowledged save, including a
                // successful replay. Failures retain the same key and input.
                // Attendance issues a fresh submission key when the success dialog closes.
                $('.select2').val(null).trigger('change');
                $('#error-alert').hide();
                $('#successModal').modal('show');
            },
            error: function (xhr) {
                showError((xhr.responseJSON || {}).msg || (xhr.responseJSON || {}).message || 'The response was interrupted. Retry without changing the form to avoid a duplicate.');
            },
            complete: function () { saving = false; if (!saved) submitButton.prop('disabled', false); }
        });
    });
    $('#successModal').on('hidden.bs.modal', function () { location.reload(); });
});
</script>
@endsection
