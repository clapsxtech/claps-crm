<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <form method="post" action="{{route('marklist.store')}}" accept-charset="UTF-8" id="createForm">
            @csrf
            <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="modal-header">
                <h4 class="modal-title">Add MarkList</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            </div>

            <div class="modal-body">
                <div class="row">
                <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Teacher Name:*</label>
                    <select class="form-control select2" id="teacher_name" style="width:100%" required name="teacher_name">
                        <option value="">Select Teacher</option>
                        @foreach($teachers as $value)
                        <option value="{{$value->id}}">{{$value->teacher_name}}</option>
                        @endforeach
                    </select>       
                </div></div>
                <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Teacher Code:*</label>
                    <input type="text" class="form-control" id="teacher_code" name="teacher_code" readonly>
                </div>
             </div>
             <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Student:</label>
                        <select class="form-control select2 " id="student_name" style="width:100%" required name="student_name">
                            <option value="">Select Student</option>
                            @foreach($students as $value)
                            <option value="{{$value->id}}">{{$value->student_name}}</option>
                            @endforeach
                        </select>
                    </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Subject:</label>
                        <select class="form-control"  id="subject" style="width:100%" required name="subject">
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
                    <input class="form-control" required="" placeholder="Chapter/Topic" autocomplete="off" name="chapter" type="text"  maxlength="15">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="name"  class="control-label">Mode of Exam:</label>
                    <select name="mode_of_exam" id="" required class="form-control">
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
                    <label for="name" class="control-label">Total Mark:*</label>
                    <input class="form-control" required="" placeholder="Total Mark" autocomplete="off" name="total_mark" type="text" onkeyup="this.value=this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')"  maxlength="15">
                </div></div>
               
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="name">Mark Scored:</label>
                        <input class="form-control" required="" placeholder="Mark Scored" autocomplete="off" name="mark_scored" type="text" onkeyup="this.value=this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" maxlength="15">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="name">Date of Examination:</label>
                        <input class="form-control" required="" value="{{old('examination_date')}}"  placeholder="YYYY/MM/DD" name="examination_date" type="date" id="date_birth" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                <div class="form-group">
                    <label for="name" class="control-label">Remarks:*</label>
                    <textarea class="form-control"  placeholder="Remarks" autocomplete="off" name="remark" type="text" maxlength="130"></textarea>
                </div></div>
            </div>
        </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary save_button">{{__('common.save')}}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{__('common.close')}}</button>
            </div>
        </form>
  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
<script>
   $(document).ready(function() {
        $('.select2').select2();
        $('#teacher_name').on('change', function() {
            var teacherId = $(this).val();
            if (teacherId) {
                $.ajax({
                    url: '{{ route("teacherinfo") }}',
                    type: 'GET',
                    data: { teacher_id: teacherId },
                    success: function(response) {
                        $('#teacher_code').val(response.teacher_code);
                    }
                });
            } else {
                $('#teacher_code').val('');
            }
        });
    });
</script>


   