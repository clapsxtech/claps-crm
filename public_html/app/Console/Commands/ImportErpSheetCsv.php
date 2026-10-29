<?php

namespace App\Console\Commands;

use App\Modules\Erp\Services\SheetCsvImporter;
use Illuminate\Console\Command;

class ImportErpSheetCsv extends Command
{
    protected $signature = 'erp:sheets-import
        {directory? : Local directory containing read-only CSV snapshots}
        {--unit= : Target ERP unit id, required for execution}
        {--actor= : Existing administrator or team-manager user id}
        {--band= : Source business band to import, for example AA or BB}
        {--execute : Commit the exact reviewed snapshot}
        {--overwrite : Replace existing canonical values with non-empty snapshot values}
        {--confirm-digest= : Exact snapshot_digest returned by preview}
        {--target-database= : Exact configured target database name}
        {--allow-production : Explicit production opt-in after all other checks}';

    protected $description = 'Preview or explicitly import read-only Sheets CSV snapshots into canonical ERP records';

    public function handle(SheetCsvImporter $importer): int
    {
        $directory = $this->argument('directory') ?: storage_path('app/erp-imports/2026-10-26');
        $band = $this->option('band') !== null ? strtoupper(trim((string) $this->option('band'))) : null;
        $preview = $importer->preview($directory, $this->option('unit') !== null ? (int) $this->option('unit') : null, $band);
        if (!$this->option('execute')) {
            $this->line(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $preview['valid'] ? self::SUCCESS : self::FAILURE;
        }

        if (!$preview['valid']) return $this->invalid('preview_has_blocking_issues');
        if (!is_string($this->option('unit')) || !ctype_digit($this->option('unit')) || (int) $this->option('unit') < 1) return $this->invalid('explicit_unit_required');
        if (!is_string($this->option('actor')) || !ctype_digit($this->option('actor')) || (int) $this->option('actor') < 1) return $this->invalid('explicit_actor_required');
        if (!in_array($band, ['AA', 'BB'], true)) return $this->invalid('explicit_band_required_aa_or_bb');
        if (!is_string($this->option('confirm-digest')) || !hash_equals($preview['snapshot_digest'], $this->option('confirm-digest'))) return $this->invalid('exact_preview_digest_required');
        if (!is_string($this->option('target-database')) || $this->option('target-database') !== (string) config('database.connections.'.config('database.default').'.database')) return $this->invalid('explicit_target_database_required');
        if (app()->environment('production') && !$this->option('allow-production')) return $this->invalid('production_opt_in_required');
        if (config('erp.migration_import_enabled', false) !== true) return $this->invalid('sheet_import_disabled');

        try {
            $result = $importer->execute($directory, (int) $this->option('unit'), (int) $this->option('actor'), (bool) $this->option('overwrite'), $band);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (\Throwable) {
            return $this->invalid('sheet_import_failed_no_partial_commit');
        }
    }

    private function invalid(string $code): int
    {
        $this->line(json_encode(['valid' => false, 'can_import' => false, 'writes' => 0, 'issues' => [['field' => 'migration', 'code' => $code, 'blocking' => true]],], JSON_PRETTY_PRINT));
        return self::FAILURE;
    }
}
