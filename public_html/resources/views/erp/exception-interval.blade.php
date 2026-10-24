@php
    $legacy = $row->starts_at === null && $row->ends_at === null && $row->timezone === null;
    $localStart = $localEnd = null;
    if (!$legacy && $row->starts_at && $row->ends_at && $row->timezone && $row->ends_at > $row->starts_at) {
        try {
            $snapshotZone = new \DateTimeZone($row->timezone);
            $localStart = (new \DateTimeImmutable($row->starts_at, new \DateTimeZone('UTC')))->setTimezone($snapshotZone);
            $localEnd = (new \DateTimeImmutable($row->ends_at, new \DateTimeZone('UTC')))->setTimezone($snapshotZone);
        } catch (\Exception $error) { $localStart = $localEnd = null; }
    }
@endphp
@if($legacy)
    Full day · participant timezone (legacy)
@elseif(!$localStart || !$localEnd)
    Invalid interval · requires correction
@else
    @if($localStart->format('H:i:s') === '00:00:00' && $localEnd->format('H:i:s') === '00:00:00' && $localEnd->format('Y-m-d') === $localStart->modify('+1 day')->format('Y-m-d'))
        Full day
    @else
        {{ $localStart->format('H:i') }}–{{ $localEnd->format('H:i') }}@if($localStart->format('Y-m-d') !== $localEnd->format('Y-m-d')) (next day)@endif
    @endif
    · {{ $row->timezone }}<br><small>{{ $row->starts_at }} → {{ $row->ends_at }} UTC</small>
@endif
