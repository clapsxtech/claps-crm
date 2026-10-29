<input type="hidden" name="{{ $context->lead_id ? 'lead_id' : 'student_id' }}" value="{{ $context->lead_id ?: $context->student_id }}">
<input type="hidden" name="teacher_id" value="{{ $teacher->id }}"><input type="hidden" name="reference" value="{{ \Illuminate\Support\Str::uuid() }}">
@foreach(['subject_id','date','time','timezone','duration_minutes','medium','grade','syllabus'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $slot[$field] }}">@endforeach
