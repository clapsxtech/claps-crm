 <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <form method="post" action="{{route('marklist.update',$marklist->id)}}" accept-charset="UTF-8" id="edit_form">

            <input name="_method" type="hidden" value="PUT">
            @csrf
            <div class="modal-header">
                <h4 class="modal-title">{{__('brand.edit_brands')}}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Teacher Code:*</label>
                    <input class="form-control" required="" placeholder="Enter Teacher Code" autocomplete="off" name="teacher_code" id="teacher_code" value="{{ $marklist->teacher_code }}" type="text" minlength="3" maxlength="15">
                </div></div>
                <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Teacher Name:*</label>
                    <input class="form-control" required="" placeholder="Enter Teacher Code" autocomplete="off" name="teacher_name" id="teacher_name" value="{{ $marklist->teacher_name }}" type="text" minlength="3" maxlength="15">
                </div>
             </div>
             <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Student:</label>
                        <select class="form-control select2 " id="student_name" style="width:100%"  required name="student_name">
                            <option value="">Select Student</option>
                            @foreach($students as $value)
                            <option value="{{ $value->id }}" {{ ($marklist->student_name == $value->id) ? 'selected' : '' }}>{{ $value->student_name }}</option>
                            @endforeach
                        </select>
                    </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Subject:</label>
                        <select class="form-control " id="subject" style="width:100%" required name="subject">
                            <option value="">Select Subject</option>
                            @foreach($subject as $value)
                            <option value="{{ $value->id }}" {{ ($marklist->subject == $value->id) ? 'selected' : '' }}>{{ $value->name }}</option>
                            @endforeach
                        </select>
                    </div>
            </div>
                <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Chapter/Topic:*</label>
                    <input class="form-control" required="" placeholder="Chapter/Topic" value="{{ $marklist->chapter }}"
                     autocomplete="off" name="chapter" type="text"  maxlength="15">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Mode of Exam:</label>
                    <select name="mode_of_exam" class="form-control">
                        <option value="">SELECT</option>
                        <option value="Viva (live)" {{ ($marklist->mode_of_exam == 'Viva (live)') ? 'selected' : '' }}>Viva (live)</option>
                        <option value="Written (live)" {{ ($marklist->mode_of_exam == 'Written (live)') ? 'selected' : '' }}>Written (live)</option>
                        <option value="written (offline)" {{ ($marklist->mode_of_exam == 'written (offline)') ? 'selected' : '' }}>written (offline)</option>
                        <option value="written + Viva(live)" {{ ($marklist->mode_of_exam == 'written + Viva(live)') ? 'selected' : '' }}>written + Viva(live)</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Total Mark:*</label>
                    <input class="form-control" required="" placeholder="Total Mark" autocomplete="off" name="total_mark" value="{{ $marklist->total_mark }}" type="text"  maxlength="15">
                </div></div>
               
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="name">Mark Scored:</label>
                        <input class="form-control" required="" placeholder="Mark Scored" autocomplete="off" name="mark_scored" value="{{ $marklist->mark_scored }}" type="text"  maxlength="15">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="name">Date of Examination:</label>
                        <input class="form-control" placeholder="YYYY/MM/DD" name="examination_date" value="{{ $marklist->examination_date }}" type="date" id="date_birth" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Remarks:*</label>
                    <textarea class="form-control" required="" placeholder="Remarks" autocomplete="off" name="remark" maxlength="130">{{ $marklist->remark }}</textarea>
                </div></div>
            </div>
        </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary save_button">{{__('common.update')}}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{__('common.close')}}</button>
            </div>
        </form>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
  <script>
    $(document).ready(function() {
        $('.select2').select2({
        });
        $('#teacher_name').prop('readonly', true); 
        $('#teacher_code').on('input', function() {
            var teacherCode = $(this).val();
            $.ajax({
                url: '{{ route("teacherinfo")}}',
                type: 'GET',
                data: { teacher_code: teacherCode },
                success: function(response) {
                    $('#teacher_name').val(response.teacher_name);
                }
            });
        });
    });
</script>