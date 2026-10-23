@extends('erp.layout')
@section('content')
<h2>My assigned tasks</h2>
<p>Only tasks assigned to you in your current staff units are shown. Complete each checklist before closing its task.</p>
@forelse($tasks as $task)
<section><h3>{{ $task->title }}</h3><p>{{ $task->notes }}</p><p>Due: {{ $task->due_on ?? 'Not specified' }} · Status: {{ $task->status }}</p>
@foreach($items->get($task->id,collect()) as $item)
<div><strong>{{ $item->title }}</strong>
@if($item->completed_at)<p>Completed {{ $item->completed_at }} UTC</p>
@else<form method="post" action="{{ route('erp.tasks.item.complete',[$task->id,$item->id]) }}">@csrf<button>Complete checklist item</button></form>@endif
</div>
@endforeach
@if($task->status!=='completed')<form method="post" action="{{ route('erp.tasks.complete',$task->id) }}">@csrf<button>Complete task</button></form>@endif
</section>
@empty<p>No tasks are currently assigned to you.</p>@endforelse
{{ $tasks->links('pagination::simple-bootstrap-4') }}
@endsection
