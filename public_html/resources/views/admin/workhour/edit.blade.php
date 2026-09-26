 <div class="modal-dialog modal-lg" role="document">
     <div class="modal-content">
         <form method="post" action="{{ route('workhour.update', $workhours->id) }}" accept-charset="UTF-8" id="edit_form">

             <input name="_method" type="hidden" value="PUT">
             @csrf
             <div class="modal-header">
                 <h4 class="modal-title">Edit Workhour</h4>
                 <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                         aria-hidden="true">×</span></button>
             </div>
             <div class="modal-body">
                 <div class="row">
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name" class="control-label">Teacher Name:*</label>
                             <input type="text" class="form-control" id="teacher_name" name="teacher_name"
                                 value="{{ $workhours->teacher_name }}" readonly>

                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name" class="control-label">Teacher Code:*</label>
                             <input type="text" class="form-control" id="teacher_code" name="teacher_code"
                                 value="{{ $workhours->teacher_code }}" readonly>
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name" class="control-label">Student:</label>
                            <input type="text" class="form-control" id="student_names" name="student_names"
                             value="{{$workhours->student_names}}" readonly>
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="student_code" class="control-label">Student Code:</label>
                             <input type="text" class="form-control" id="student_code"  value="{{ $workhours->student_code }}" name="student_code" readonly>
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name" class="control-label">Subject:</label>
                             <select class="form-control select2" id="subject" style="width:100%" required
                                 name="subject">
                                 <option value="">Select Subject</option>
                                 @foreach ($subject as $value)
                                     <option value="{{ trim($value->name) }}"
                                         {{ trim($workhours->subject) === trim($value->name) ? 'selected' : '' }}>
                                         {{ $value->name }}
                                     </option>
                                 @endforeach
                             </select>
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name">Date:</label>
                             <input class="form-control" required="" placeholder="YYYY/MM/DD" name="date"
                                 type="date" id="date" autocomplete="off" value="{{ $workhours->date }}">
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name" class="control-label">Chapter:*</label>
                             <input class="form-control" required="" placeholder="Chapter" autocomplete="off"
                                 name="chapter" type="text" value="{{ $workhours->chapter }}" maxlength="15">
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name" class="control-label">Topic:*</label>
                             <input class="form-control" required="" placeholder="Topic" autocomplete="off"
                                 name="topic" type="text" value="{{ $workhours->topic }}" maxlength="15">
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="hour" class="control-label">Hour Taken:</label>
                             <select class="form-control select2" id="hour_taken" style="width:100%" required
                                 name="hour_taken">
                                 <option value="">Choose</option>
                                 <option value="0.5" {{ $workhours->hour_taken === '0.5' ? 'selected' : '' }}>0.5
                                 </option>
                                 <option value="0.75" {{ $workhours->hour_taken === '0.75' ? 'selected' : '' }}>0.75
                                 </option>
                                 <option value="1" {{ $workhours->hour_taken === '1' ? 'selected' : '' }}>1
                                 </option>
                                  <option value="1.25" {{ $workhours->hour_taken === '1.25' ? 'selected' : '' }}>1.25
                                 </option>
                                 <option value="1.5" {{ $workhours->hour_taken === '1.5' ? 'selected' : '' }}>1.5
                                 </option>
                                 <option value="2" {{ $workhours->hour_taken === '2' ? 'selected' : '' }}>2
                                 </option>
                             </select>

                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name" class="control-label">Hour Rate:*</label>
                             <input class="form-control" required="" placeholder="hour_payment"
                                 autocomplete="off" name="hour_payment" id="hour_payment" type="text"
                                 value="{{ $workhours->hour_payment }}" maxlength="15" >
                         </div>
                     </div>
                     <div class="col-md-6 total-amount">
                        <div class="form-group">
                            <label for="total_amount" class="control-label">payment Per Hour:</label>
                            <input type="text" class="form-control" id="payment_per_hour" name="payment_per_hour"
                                value="{{ $workhours->payment_per_hour }}" >
                        </div>
                    </div>
                    <div class="col-md-6 total-amount">
                        <div class="form-group">
                            <label for="total_amount" class="control-label">Profit Per Hour:</label>
                            <input type="text" class="form-control" id="profit_per_hour" name="profit_per_hour"
                                value="{{ $workhours->profit_per_hour }}" >
                        </div>
                    </div>
                     <div class="col-md-6 total-amount">
                         <div class="form-group">
                             <label for="total_amount" class="control-label">Total Amount:</label>
                             <input type="text" class="form-control" id="total_amount" name="total_amount"
                                 value="{{ $workhours->total_amount }}" >
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name" class="control-label">Time Stamp:</label>
                             <input class="form-control" required="" name="date_submitted" type="text"
                                 id="date_submitted" autocomplete="off" value="{{ $workhours->date_submitted }}">
                         </div>
                     </div>


                     <div class="col-md-6">
                         <div class="form-group">
                             <label for="name">Do you assigned any home work to the student ?:</label>
                             <div class="radio">
                                 <label><input type="radio" name="homework_status"
                                         value="yes"{{ $workhours->homework_status === 'yes' ? 'checked' : '' }}>
                                     Yes</label>
                             </div>
                             <div class="radio">
                                 <label><input type="radio" name="homework_status"
                                         value="no"{{ $workhours->homework_status === 'no' ? 'checked' : '' }}>
                                     No</label>
                             </div>
                         </div>
                     </div>
                     <div class="modal-footer">
                         <button type="submit"
                             class="btn btn-primary save_button">{{ __('common.update') }}</button>
                         <button type="button" class="btn btn-default"
                             data-dismiss="modal">{{ __('common.close') }}</button>
                     </div>
                 </div>
             </div>
         </form>
     </div><!-- /.modal-content -->
 </div><!-- /.modal-dialog -->

 <script>
     $(document).ready(function() {
         $('.select2').select2();

         function fetchStudents(teacherName) {
             $.ajax({
                 url: '{{ route('studentinfoedit') }}',
                 type: 'GET',
                 data: {
                     teacher_name: teacherName
                 },
                 success: function(response) {
                     $('#student_names').empty().append('<option value="">Select Student</option>');

                     $.each(response.students, function(key, value) {
                         $('#student_names').append('<option value="' + value.student_name +
                             '" data-student-code="' + value.student_code +
                             '" data-hour-payment="' + value.hour_payment +
                             '" data-profit-per-hour="' + value.profit_per_hour +
                             '" data-payment-per-hour="' + value.payment_per_hour +
                             '">' + value.student_name + '</option>');
                     });
                     $('#teacher_code').val(response.teacher_code);

                     var selectedStudent = '{{ $workhours->student_name }}';
                     $('#student_names').val(selectedStudent).trigger('change');
                 },
                 error: function(xhr, status, error) {
                     console.error('AJAX Error: ' + error);
                 }
             });
         }

         var initialTeacherName =
         '{{ $workhours->teacher_name }}'; 
         if (initialTeacherName) {
             fetchStudents(initialTeacherName);
         }

         $('#teacher_name').on('input', function() {
             var teacherName = $(this).val().trim();

             if (teacherName) {
                 fetchStudents(teacherName);
             } else {
                 $('#student_names').empty().append('<option value="">Select Student</option>');
                 $('#teacher_code').val('');
             }
         });


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
