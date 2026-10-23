<?php

namespace App\Console\Commands;

use App\Modules\Erp\Services\MigrationPreview;
use Illuminate\Console\Command;

class PreviewErpMigration extends Command
{
    protected $signature = 'erp:migration-preview {file : Local JSON envelope containing rows and references}';
    protected $description = 'Validate a local ERP migration batch without importing or posting financial events';

    public function handle(MigrationPreview $preview): int
    {
        $path = $this->argument('file');
        if (!is_string($path) || strpos($path, '://') !== false || !is_file($path) || !is_readable($path)) return $this->invalid('unreadable_local_file');
        $bytes = @file_get_contents($path, false, null, 0, 5242881);
        if ($bytes === false) return $this->invalid('unreadable_local_file');
        if (strlen($bytes) > 5242880) return $this->invalid('file_too_large');
        try {
            $envelope = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            return $this->invalid('invalid_json');
        }
        if (!is_array($envelope) || !isset($envelope['rows'], $envelope['references']) || !is_array($envelope['rows']) || !is_array($envelope['references'])) return $this->invalid('invalid_envelope');
        $rows = $envelope['rows'];
        if ($rows && array_keys($rows) !== range(0, count($rows) - 1)) return $this->invalid('rows_must_be_list');
        if (count($rows) > 5000) return $this->invalid('too_many_rows');
        foreach (['unit_codes', 'subject_codes'] as $key) {
            if (!isset($envelope['references'][$key]) || !is_array($envelope['references'][$key])) return $this->invalid('invalid_reference_catalog');
        }
        try {
            $result = $preview->preview($rows, $envelope['references']);
        } catch (\Throwable $e) {
            // Never expose exception messages: malformed input can include personal data.
            return $this->invalid('invalid_batch_structure');
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $result['valid'] ? 0 : 1;
    }

    private function invalid(string $code): int
    {
        $this->line(json_encode([
            'dry_run' => true, 'can_import' => false, 'valid' => false, 'writes' => 0,
            'counts' => [], 'totals' => null,
            'issues' => [['row' => 0, 'field' => 'input', 'code' => $code, 'blocking' => true]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 1;
    }
}
