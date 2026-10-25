<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mark Upload Form</title>
  <link rel="stylesheet" href="{{asset('frontent_form/bootstrap.min.css')}}">
  <link rel="stylesheet" href="{{asset('adminlte/select2.min.css')}}">
  <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

  <style>
    /* Add your custom styles here */
    .gradient-custom {
      background: #c6c8c8;
      background: -webkit-linear-gradient(to bottom right, rgba(6, 52, 54, 1), rgba(198, 200, 200, 1));
      background: linear-gradient(to bottom right, rgba(6, 52, 54, 1), rgba(198, 200, 200, 1));
    }
    /* Additional styles for card and form */
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
    cursor: pointer; /* Show pointer cursor on label for better interaction */
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
   #error-alert,
    #successModal .modal-content {
        text-align: center;
    }
  </style>
</head>
<body class="public-page">
  <section class="h-100 gradient-custom">
    <div class="container py-5 h-100">
      <div class="row justify-content-center align-items-center h-100">
        <div class="col-lg-8 col-xl-6">
          <div class="card shadow-3 card-registration">
            <div class="card-body p-4 p-md-4">
              <div style="display: flex; align-items: center;">
                <h3 class="mb-0" style="flex: 1;">
                  <span style="font-weight: bold; color: #1c484a;">Mark Upload</span> </h3>
                <img src="{{'img/claps-log.jpg'}}" alt="Logo"  style="max-width: 35%;">
              </div>
              <div class="modal fade" id="successModal"  aria-labelledby="successModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="successModalLabel">Success</h5>
                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                      </button>
                    </div>
                    <div class="modal-body">
                      Mark Added Successfully!
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-success" data-dismiss="modal">OK</button>
                    </div>
                  </div>
                </div>
              </div>
              <form  class="px-md-2" action="{{ route('upload-result') }}" method="POST" id="markUploadForm">
                @csrf
                <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="teacher_name" class="control-label">Teacher Name:*</label>
                      <select class="form-control select2" id="teacher_name" style="width:100%"
                     required name="teacher_name">
                        <option value="">Select Teacher</option>
                        @foreach($teachers as $value)
                        <option value="{{$value->teacher_name}}" data-teacher-name="{{$value->teacher_name}}">{{$value->teacher_name}}</option>
                        @endforeach
                    </select>
                  </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Teacher Code:</label>
                      <input type="text" class="form-control" id="teacher_code" name="teacher_code" readonly>
                  </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Teacher verification code:*</label>
                      <input class="form-control" required="" placeholder="DD/MM/YYYY"
                       autocomplete="off" name="password" id="password" type="password" >
                      <small class="form-text text-muted">Select your teacher name and enter your verification code, then load your students.</small>
                      <button type="button" class="btn btn-outline-success mt-2" id="verify-teacher">Verify and load students</button>
                  </div>
                  </div>
                  <div class="col-md-6 mb-4">
                    <label for="student_name" class="control-label">Students:*</label>
                    <select class="form-control select2" id="student_name" style="width:100%" required name="student_name">
                      <option value="">Select Student</option>
                    </select>
                </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="student_code" class="control-label">Student Code:</label>
                      <input type="text" class="form-control" id="student_code" name="student_code" readonly>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Subject:*</label>
                          <select class="form-control select2"  id="subject" style="width:100%" required name="subject">
                              <option value="">Select Subject</option>
                              @foreach($subject as $value)
                              <option value="{{$value->id}}">{{$value->name}}</option>
                              @endforeach
                          </select>
                  </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Chapter/Topic:*</label>
                      <input class="form-control" required="" placeholder="Chapter/Topic" autocomplete="off" name="chapter" type="text"  maxlength="30">
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name"  class="control-label">Mode of Exam:*</label>
                      <select class="form-control select2"  id="mode_of_exam" style="width:100%" required name="mode_of_exam">
                        <option value="">SELECT</option>
                        <option value="Viva (live)">Viva (live)</option>
                        <option value="Written (live)">Written (live)</option>
                        <option value="written (offline)">written (offline)</option>
                        <option value="written + Viva(live)">written + Viva(live)</option>
                    </select>
                  </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name">Date of Examination:*</label>
                      <input class="form-control" required="" value="{{old('examination_date')}}"  placeholder="YYYY/MM/DD" name="examination_date" type="date"  autocomplete="off">

                  </div>
                  </div>
                </div> <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name">Mark Scored:*</label>
                      <input class="form-control" required="" placeholder="Mark Scored" autocomplete="off" name="mark_scored" type="text" onkeyup="this.value=this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')"  maxlength="15">
                  </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Out of:*</label>
                      <input class="form-control" required="" placeholder="Out of" autocomplete="off" name="total_mark" type="text" onkeyup="this.value=this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" maxlength="15">
                  </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Remarks:*</label>
                      <textarea class="form-control"  placeholder="Remarks" autocomplete="off" name="remark" type="text" maxlength="130"></textarea>
                    </div>
                  </div>

                  <div id="error-alert" class="alert alert-danger alert-dismissible fade show" style="display: none;" role="alert">
                    Error occurred while submitting the form. Please try again later.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                </div>
                <button type="submit" class="btn btn-success btn-lg mb-1">Submit</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</body>
</html>

<script src="{{ asset('browser-vendor/jquery.min.js') }}"></script>
<script src="{{ asset('browser-vendor/bootstrap.bundle.min.js') }}"></script>

<script src="{{ asset('browser-vendor/select2.min.js') }}"></script>
<script>
$(function () {
    $('.select2').select2();
    var form = $('#markUploadForm');
    var pendingLookup = null;

    function showError(message) {
        $('#error-alert').text(message).show();
    }

    function clearAssignment() {
        if (pendingLookup) { pendingLookup.abort(); pendingLookup = null; }
        $('#student_name').empty().append(new Option('Verify your teacher identity first', '')).trigger('change');
        $('#student_code, #teacher_code').val('');
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
        pendingLookup = $.ajax({
            url: '{{ route("studentinforesult") }}',
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
                $('#student_name').trigger('change');
                $('#error-alert').hide();
            },
            error: function (xhr, status) {
                if (status !== 'abort') {
                    showError((xhr.responseJSON || {}).msg || (xhr.responseJSON || {}).message || 'Teacher verification failed.');
                }
            },
            complete: function () { pendingLookup = null; button.prop('disabled', false); }
        });
    });

    form.on('submit', function (event) {
        event.preventDefault();
        var submitButton = form.find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: '{{ route("upload-result") }}',
            type: 'POST',
            headers: { Accept: 'application/json' },
            data: form.serialize(),
            success: function (response) {
                if (!response.success) { showError(response.msg || 'Unable to save the record.'); return; }
                form[0].reset();
                // Advance the key only after an acknowledged save, including a
                // successful replay. Failures retain the same key and input.
                form.find('[name="submission_key"]').val(response.next_submission_key).attr('value', response.next_submission_key);
                $('.select2').val(null).trigger('change');
                $('#error-alert').hide();
                $('#successModal').modal('show');
            },
            error: function (xhr) {
                showError((xhr.responseJSON || {}).msg || (xhr.responseJSON || {}).message || 'The response was interrupted. Retry without changing the form to avoid a duplicate.');
            },
            complete: function () { submitButton.prop('disabled', false); }
        });
    });
});
</script>
