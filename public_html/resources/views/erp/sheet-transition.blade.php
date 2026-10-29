@extends('erp.layout')
@section('content')
<div class="page-heading"><div><h1>From Sheets</h1><p>Validated copies of the records your team maintains in Google Sheets.</p></div></div>
<p>Update the original Sheet while the transition is in progress. These records do not create ERP charges, receipts, salary accruals or scheduled lessons.</p>
<form method="get" class="filter-bar">
    <label>Team <select name="unit">@foreach($units as $item)<option value="{{ $item->id }}" @selected($unit===$item->id)>{{ $item->name }}</option>@endforeach</select></label>
    <label>Source <select name="source">@foreach($sources as $sourceKey=>$source)<option value="{{ $sourceKey }}" @selected($key===$sourceKey)>{{ $source['label'] }}</option>@endforeach</select></label>
    <label>Exact record code <input name="q" value="{{ request('q') }}" maxlength="100" placeholder="CCA…, CT… or attendance ID"></label>
    @if($administrator)<label><input type="checkbox" name="review" value="1" @checked($review)> Latest refresh review (all teams)</label>@endif
    <button type="submit" class="button">Show records</button>
</form>
<p><a href="https://docs.google.com/spreadsheets/d/{{ $sources[$key]['spreadsheet_id'] }}/edit#gid={{ $sources[$key]['gid'] }}" target="_blank" rel="noopener noreferrer">Open original Sheet</a></p>
@if($review)<p class="error">Administrator review: this refresh may contain unresolved rows. It has not replaced the published copy unless its status is ready.</p>@endif
@if($stale)<p class="error" role="status">{{ $run ? 'This copy is older than the refresh target. Check the original Sheet before acting.' : 'No validated copy is available yet.' }}</p>@endif
<dl><dt>Last successful refresh ({{ config('app.timezone') }})</dt><dd>{{ $states[$key]->last_success_at ?? 'Never' }}</dd><dt>Latest attempt ({{ config('app.timezone') }})</dt><dd>{{ $states[$key]->last_attempt_at ?? 'Never' }} · {{ $states[$key]->last_status ?? 'Not started' }}</dd></dl>
@if($run)
<p>Showing {{ $run->status }} snapshot · {{ $records->total() }} matching rows.@if($administrator) {{ $run->row_count }} source rows · {{ $run->issue_count }} rows requiring review.@endif @if($run->reason) Reason: {{ str_replace('_',' ',$run->reason) }}.@endif</p>
@if($review)<p>Correct unresolved rows in the source Sheet, then refresh again. A removed identity requires an operator to review the exact preview digest. Refreshes are published only when all rows pass.</p>@endif
@endif
@if($key==='timetable')<p>Times and teacher labels are displayed as written in the Sheet. They have not been converted into dated lessons or checked for timetable conflicts.</p>@endif
@if($key==='balances')<p>Balances are reported by the source Sheet. They are not reconciled ERP ledger balances.</p>@endif
<div style="overflow-x:auto" tabindex="0" role="region" aria-label="Imported Sheet records"><table><thead><tr><th>Source row</th><th>Identity</th>@if($review)<th>Review reason</th>@endif @foreach($columns as $column)<th>{{ ucwords(str_replace('_',' ',$column)) }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $row->source_row }}</td><td>{{ $row->source_identity }}</td>@if($review)<td>{{ $row->issue ? str_replace('_',' ',$row->issue) : 'Passed' }}</td>@endif @foreach($columns as $column)<td>{{ $row->data[$column] ?? '' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($columns)+($review?3:2) }}">No records to show for this selection.</td></tr>@endforelse
</tbody></table></div>
{{ $records->links() }}
@endsection
