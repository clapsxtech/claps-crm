@foreach(array_intersect_key($seed,array_flip(['student_id','lead_id','demo_id','subject_id','date','time','timezone','duration_minutes','medium','grade','syllabus','teacher_q','search','include_unverified'])) as $field=>$value)
@if($value!==null && $value!=='')<input type="hidden" name="return_filters[{{ $field }}]" value="{{ $value }}">@endif
@endforeach
