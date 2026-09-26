<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <form method="post" action="{{ route('workhour.store') }}" accept-charset="UTF-8" id="createForm">
            @csrf
            <div class="modal-header">
                <h4 class="modal-title">Work Hour</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name" class="control-label">Teacher Name:*</label>
                            <select class="form-control select2" id="teacher_name" style="width:100%" required
                                name="teacher_name">
                                <option value="">Select Teacher</option>
                                @foreach ($teachers as $value)
                                    <option data-teacher-name="{{ $value->teacher_name }}">{{ $value->teacher_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name" class="control-label">Teacher Code:*</label>
                            <input type="text" class="form-control" id="teacher_code" name="teacher_code" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name" class="control-label">Student:</label>
                            <select class="form-control select2" id="student_name" style="width:100%" required
                                name="student_name">
                                <option value="">Select Student</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="student_code" class="control-label">Student Code:</label>
                            <input type="text" class="form-control" id="student_code" name="student_code" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name" class="control-label">Subject:</label>
                            <select class="form-control select2" id="subject" style="width:100%" required
                                name="subject">
                                <option value="">Select Subject</option>
                                @foreach ($subject as $value)
                                    <option value="{{ $value->id }}">{{ $value->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Date:</label>
                            <input class="form-control" required="" value="{{ old('examination_date') }}"
                                placeholder="YYYY/MM/DD" name="date" type="date" id="date"
                                autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name" class="control-label">Chapter:*</label>
                            <input class="form-control" required="" placeholder="Chapter" autocomplete="off"
                                name="chapter" type="text" maxlength="15">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name" class="control-label">Topic:*</label>
                            <input class="form-control" required="" placeholder="Topic" autocomplete="off"
                                name="topic" type="text" maxlength="15">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="hour" class="control-label">Hour Taken:</label>
                            <select class="form-control select2" id="hour_taken" style="width:100%" required
                                name="hour_taken">
                                <option value="">Choose</option>
                                <option value="0.5">0.5</option>
                                <option value="0.75">0.75</option>
                                <option value="1">1</option>
                                <option value="1.25">1.25</option>
                                <option value="1.5">1.5</option>
                                <option value="2">2</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Do you assigned any home work to the student ?:</label>
                            <div class="radio">
                                <label><input type="radio" name="homework_status" value="yes"> Yes</label>
                            </div>
                            <div class="radio">
                                <label><input type="radio" name="homework_status" value="no"> No</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="name" class="control-label">Password:*</label>
                            <input class="form-control" required="" placeholder="DD/MM/YYYY" autocomplete="off"
                                name="password" id="password" type="text">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary save_button">{{ __('common.save') }}</button>
                        <button type="button" class="btn btn-default"
                            data-dismiss="modal">{{ __('common.close') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    $(document).ready(function() {
        $('.select2').select2();
        $('#teacher_name').on('change', function() {
            var selectedOption = $(this).find(':selected');
            var teacherId = selectedOption.val();
            var teacherName = selectedOption.data('teacher-name');

            if (teacherName) {
                $.ajax({
                    url: '{{ route('studentinfo') }}',
                    type: 'GET',
                    data: {
                        teacher_name: teacherName
                    },
                    success: function(response) {
                        $('#student_name').empty();
                        $('#student_name').append(
                            '<option value="">Select Student</option>');

                        $.each(response.students, function(key, value) {
                            $('#student_name').append('<option value="' + value
                                .student_name + '" data-student-code="' + value
                                .student_code + '" data-student-name="' + '">' +
                                value.student_name + '</option>');
                        });
                        $('#teacher_code').val(response.teacher_code);
                    }
                });
            } else {
                $('#student_name').empty().append('<option value="">Select Student</option>');
                $('#teacher_code').val('');
            }
        });

        $('#student_name').on('change', function() {
            var selectedStudent = $(this).find(':selected');
            var studentCode = selectedStudent.data('student-code');
            $('#student_code').val(studentCode);
        });
        $(document).ready(function() {
            $('#password').on('input', function(e) {
                var inputValue = $(this).val();
                var key = e.originalEvent.data;

                if (key === null) {
                    var newValue = inputValue.slice(0, -1);
                    $(this).val(newValue);
                } else {
                    if (/^\d$/.test(key)) {
                        if (inputValue.length === 2 || inputValue.length === 5) {
                            $(this).val(inputValue + '/');
                        }
                        if (inputValue.length > 10) {
                            $(this).val(inputValue.slice(0, 10));
                        }
                    } else {
                        $(this).val(inputValue.replace(/[^\d]/g, ''));
                    }
                }
            });
        });
    });
</script>
