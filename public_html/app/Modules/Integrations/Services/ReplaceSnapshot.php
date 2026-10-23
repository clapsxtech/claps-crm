<?php
namespace App\Modules\Integrations\Services;

use Illuminate\Support\Facades\DB;

/** Receives fully parsed rows; snapshot mutations are all-or-nothing. */
class ReplaceSnapshot
{
    public function replace(string $model, array $records): void
    {
        if (!$records) SheetSnapshotReader::invalid();
        DB::transaction(function () use ($model, $records) {
            $model::query()->delete(); // Never TRUNCATE: MySQL TRUNCATE commits implicitly.
            foreach (array_chunk($records, 500) as $chunk) {
                $timestamp = now();
                $model::insert(array_map(fn ($row) => array_merge($row, ['created_at' => $timestamp, 'updated_at' => $timestamp]), $chunk));
            }
        });
    }

    /** Preserve numeric IDs for retained stable codes; never reset the sequence. */
    public function reconcile(string $model, array $records, string $code, string $name): void
    {
        if (!$records) SheetSnapshotReader::invalid();
        $records = $this->unique($records, $code, $name);
        DB::transaction(function () use ($model, $records, $code, $name) {
            $existing = $model::query()->lockForUpdate()->get();
            $seen = [];
            foreach ($existing as $row) {
                $key = trim((string) $row->getAttribute($code));
                if ($key === '' || isset($seen[$key])) SheetSnapshotReader::invalid();
                $seen[$key] = $row;
            }
            foreach ($records as $record) {
                if (isset($seen[$record[$code]])) $seen[$record[$code]]->update($record);
                else $model::create($record);
            }
            $model::whereNotIn($code, array_column($records, $code))->delete();
        });
    }

    public function unique(array $records, string $code, string $name): array
    {
        $unique = []; $names = [];
        foreach ($records as $record) {
            SheetSnapshotReader::requireFields($record, [$code, $name]);
            $key = $record[$code]; $label = mb_strtolower($record[$name]);
            if (isset($unique[$key]) && $unique[$key] !== $record) SheetSnapshotReader::invalid();
            if (isset($names[$label]) && $names[$label] !== $key) SheetSnapshotReader::invalid();
            $unique[$key] = $record; $names[$label] = $key;
        }
        return array_values($unique);
    }
}
