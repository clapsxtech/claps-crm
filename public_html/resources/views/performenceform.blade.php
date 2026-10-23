<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Performance</title>
    <link rel="stylesheet" href="{{ asset('frontent_form/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/select2.min.css') }}">
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
            cursor: pointer;
            /* Show pointer cursor on label for better interaction */
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
                                    <span style="font-weight: bold; color: #1c484a;">Teacher Performance</span>
                                </h3>
                                <img src="{{ 'img/claps-log.jpg' }}" alt="Logo" style="max-width: 35%;">
                            </div>
                            <div class="modal fade" id="successModal" aria-labelledby="successModalLabel"
                                aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="successModalLabel">Success</h5>
                                            <button type="button" class="close" data-dismiss="modal"
                                                aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            Added Successfully!
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-success"
                                                data-dismiss="modal">OK</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <form class="px-md-2" action="{{ route('upload-result') }}" method="POST"
                                id="markUploadForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="teacher_name" class="control-label">Teacher Name:*</label>
                                            <select class="form-control select2" id="teacher_name" style="width:100%"
                                                required name="teacher_name">
                                                <option value="">Select Teacher</option>
                                                @foreach ($teachers as $value)
                                                    <option data-teacher-name="{{ $value->teacher_name }}">
                                                        {{ $value->teacher_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name" class="control-label">Teacher Code:</label>
                                            <input type="text" class="form-control" id="teacher_code"
                                                name="teacher_code" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label for="student_name" class="control-label">Students:*</label>
                                        <select class="form-control select2" id="student_name" style="width:100%"
                                            required name="student_name">
                                            <option value="">Select Student</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="student_code" class="control-label">Student Code:</label>
                                            <input type="text" class="form-control" id="student_code"
                                                name="student_code" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name" class="control-label">Topic:*</label>
                                            <select class="form-control select2" id="topic" style="width:100%"
                                                required name="topic">
                                                <option value="">Select Topic</option>
                                                @foreach ($topics as $value)
                                                    <option value="{{ $value->id }}">{{ $value->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name" class="control-label">Remarks:*</label>
                                            <textarea class="form-control" placeholder="Remarks" autocomplete="off" name="remark" type="text" maxlength="130"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="password" class="control-label">Teacher verification code:*</label>
                                            <input class="form-control" required autocomplete="off" name="password"
                                                id="password" type="password">
                                        </div>
                                    </div>
                                    <div id="error-alert" class="alert alert-danger alert-dismissible fade show"
                                        style="display: none;" role="alert">
                                        Error occurred while submitting the form. Please try again later.
                                        <button type="button" class="close" data-dismiss="alert"
                                            aria-label="Close">
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
    $('#teacher_name').on('change', function() {
        var selectedOption = $(this).find(':selected');
        var teacherId = selectedOption.val();
        var teacherName = selectedOption.data('teacher-name');

        if (teacherName) {
            $.ajax({
                url: '{{ route('studentinforesult') }}',
                type: 'GET',
                data: {
                    teacher_name: teacherName
                },
                success: function(response) {
                    $('#student_name').empty();
                    $('#student_name').append('<option value="">Select Student</option>',
                    '<option value="Demo Student">Demo Student</option>');

                    $.each(response.students, function(key, value) {
                        $('#student_name').append('<option value="' + value.student_name +
                            '" data-student-code="' + value.student_code +
                            '" data-student-name="' + '">' + value.student_name +
                            '</option>');
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
        var studentName = selectedStudent.val();
        var studentCode = studentName === 'Demo Student' ? 'demo' : selectedStudent.data('student-code');
        $('#student_code').val(studentCode);
    });
    $(document).ready(function() {
        $('#markUploadForm').submit(function(event) {
            event.preventDefault();
            var form = $(this);
            var submitButton = $('button[type="submit"]');

            submitButton.prop('disabled', true);

            $.ajax({
                url: '{{ route('performnce-upload') }}',
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    console.log(response);
                    if (response.success) {
                        form[0].reset();
                        $('#successModal').modal('show');
                        $('.select2').val(null).trigger('change');

                        submitButton.prop('disabled', false);
                    } else {
                        showErrorAlert(response.msg);
                        submitButton.prop('disabled', false);
                    }
                },
                error: function(err) {
                    console.error(err);

                    showErrorAlert('An error occurred while submitting the form.');

                }

            });
        });

        function showErrorAlert(message) {
            $('#error-alert').text(message);
            $('#error-alert').fadeIn();

            setTimeout(function() {
                $('#error-alert').fadeOut();
            }, 5000);
        }
    });
</script>

