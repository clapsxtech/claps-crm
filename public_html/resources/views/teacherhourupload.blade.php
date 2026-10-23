<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance</title>
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
                  <span style="font-weight: bold; color: #1c484a;">Attendance</span>                </h3>
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
                      Work Hour Upload Successfully!<br>
                      (Within 1 minute, it will be reflected in your report)           
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-success" data-dismiss="modal">OK</button>
                    </div>
                  </div>
                </div>
              </div>
              <p class="text-muted">Select a teacher and enter the password to load assigned students. Rates and totals are calculated securely when you submit.</p>
<form  class="px-md-2" action="{{ route('upload-workhour') }}" method="POST" id="markUploadForm">
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
                        <option  data-teacher-name="{{$value->teacher_name}}">{{$value->teacher_name}}</option>
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
                  <div class="col-md-6 hour-rate-div">
                    <div class="form-group">
                        <label for="hour_payment" class="control-label">ip-adrress:</label>
                        <input type="text" class="form-control" id="hour_payment" name="hour_payment" readonly>
                    </div>
                </div>
                <div class="col-md-6 hour-rate-div">
                  <div class="form-group">
                      <label for="profit_per_hour" class="control-label">ipv-adrress:</label>
                      <input type="text" class="form-control" id="profit_per_hour" name="profit_per_hour" readonly>
                  </div>
              </div>
              <div class="col-md-6 hour-rate-div">
                <div class="form-group">
                    <label for="payment_per_hour" class="control-label">ipm-adrress:</label>
                    <input type="text" class="form-control" id="payment_per_hour" name="payment_per_hour" readonly>
                </div>
            </div>
              <div class="col-md-6 total-amount">
                <div class="form-group">
                    <label for="total_amount" class="control-label">Total Amount:</label>
                    <input type="text" class="form-control" id="total_amount" name="total_amount"  readonly>
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
                      <label for="name">Date of Class:*</label>
                      <input class="form-control" required="" value="{{old('examination_date')}}"
                             placeholder="YYYY/MM/DD" name="date" type="date" id="date" autocomplete="off"
                             max="{{ date('Y-m') }}-{{ date('t') }}">
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Chapter:*</label>
                      <input class="form-control" required="" placeholder="Chapter"
                       autocomplete="off" name="chapter" type="text"></div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Topic:*</label>
                      <input class="form-control" required="" placeholder="Topic"
                       autocomplete="off" name="topic" type="text">
                  </div>
                  </div>
                </div> <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="hour"  class="control-label">Hours Taken:*</label>
                      <select class="form-control select2"  id="hour_taken" style="width:100%" required name="hour_taken">
                          <option value="">Choose</option>
                          <option value="0.5">30 Minutes</option>
                          <option value="0.75">45 Minutes</option>
                          <option value="1">1 Hour</option>
                          <option value="1.25">1 Hour & 15 Minutes</option>
                          <option value="1.5">1 Hour & 30 Minutes</option>
                          <option value="2">2 Hours</option>
                      </select>
                  </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name">Did you assigned any home work to the student ?:*</label>
                      <div class="radio-inline">
                          <label class="radio-label"><input type="radio" name="homework_status" value="yes"> Yes</label>
                      </div>
                      <div class="radio-inline">
                          <label class="radio-label"><input type="radio" name="homework_status" value="no"> No</label>
                      </div>
                  </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="name" class="control-label">Password:*('dd/mm/yyyy')</label>
                      <input class="form-control" required="" placeholder="DD/MM/YYYY"
                       autocomplete="off" name="password" id="password" type="password" >
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>
<script>
  $('.select2').select2();
      $('#teacher_name, #password').on('change', function() {
          var teacherName = $('#teacher_name').find(':selected').data('teacher-name') || $('#teacher_name').val();
          var password = $('#password').val();
          $('#student_name').empty().append(new Option('Select Student', ''));
          $('#teacher_code, #student_code').val('');
          if (!teacherName || !password) return;
          $.ajax({
              url: '{{ route("studentinfom") }}', type: 'POST',
              data: { teacher_name: teacherName, password: password, _token: $('input[name="_token"]').val() },
              success: function(response) {
                  $.each(response.students || [], function(_, student) {
                      $(new Option(student.student_name, student.student_name))
                          .attr('data-student-code', student.student_code).appendTo('#student_name');
                  });
                  $('#teacher_code').val(response.teacher_code);
              },
              error: function(xhr) { $('#error-alert').text((xhr.responseJSON || {}).message || 'Teacher verification failed.').show(); }
          });
      });
      $('#student_name').on('change', function() {
          var selectedStudent = $(this).find(':selected');
          var studentCode = selectedStudent.data('student-code');
          var hourpayment = selectedStudent.data('hour-payment');
          var profitperhour = selectedStudent.data('profit-per-hour');
          var paymentperhour = selectedStudent.data('payment-per-hour');

          $('#student_code').val(studentCode);
          $('#hour_payment').val(hourpayment);
          $('#profit_per_hour').val(profitperhour);
          $('#payment_per_hour').val(paymentperhour);


      });
      $(document).ready(function() {
        $('#markUploadForm').submit(function (event) {
        event.preventDefault(); 
        var form = $(this);
        var submitButton = $('button[type="submit"]'); 
             submitButton.prop('disabled', true);
        $.ajax({
            url: '{{ route("upload-workhour") }}',
            type: 'POST',
            data: form.serialize(),
            success: function (response) {
                console.log(response);
                if (response.success) {
                    form[0].reset();
                    $('#successModal').modal('show');
                    $('.select2').val(null).trigger('change');
                } else {
                    showErrorAlert(response.msg);
                     submitButton.prop('disabled', false);
                }
            },
            error: function (err) {
                console.error(err);
                showErrorAlert((err.responseJSON || {}).message || 'Unable to submit. Correct the details or retry with the same form.');
                                $('button[type="submit"]').prop('disabled', false);

            }
        }); 
    });
    $('#successModal').on('hidden.bs.modal', function () {
    location.reload(true);
    });
    function showErrorAlert(message) {
        $('#error-alert').text(message);
        $('#error-alert').fadeIn();

        setTimeout(function () {
            $('#error-alert').fadeOut();
        }, 5000);
    }
});
$(document).ready(function() {
    $('.hour-rate-div').hide();
    $('.total-amount').hide();

    $('#hour_taken').on('change', function() {
        var hourTaken = parseFloat($(this).val());
        var hourPayment = parseFloat($('#hour_payment').val());
        if (!isNaN(hourPayment)) {
            var totalAmount = hourTaken * hourPayment;
            $('#total_amount').val(totalAmount.toFixed(2));
        } else {
            console.error("Hour payment is not a valid number.");
        }
    });
    });
  
</script>
 
