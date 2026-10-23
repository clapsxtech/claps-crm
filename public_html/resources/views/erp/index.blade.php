@extends('erp.layout')
@section('content')
<p><a href="{{ route('erp.teacher.index') }}">Teacher lesson attendance</a></p><h2>Your business units</h2><p>Separate teacher pools and explicit administrator access. Existing Sheets and legacy records are not automatically imported.</p>
@forelse($units as $unit)<section><h2><a href="{{ route('erp.workspace',$unit->id) }}">{{ $unit->name }}</a></h2><p>{{ $unit->phone }} · {{ $unit->timezone }}</p></section>@empty<p class="notice">No unit administrator membership is configured for this account. Ask your installation administrator to provision AA or BB using the documented setup command.</p>@endforelse
@endsection