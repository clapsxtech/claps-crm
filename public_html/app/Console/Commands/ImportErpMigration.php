<?php
namespace App\Console\Commands;

use App\Modules\Erp\Services\MigrationImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportErpMigration extends Command
{
    protected $signature = 'erp:migration-import {file : Local JSON envelope} {--execute : Commit explicitly approved snapshot} {--rehearse : Exercise import and reconciliation then roll back; non-production only} {--reconcile : Read-only check against an existing exact committed snapshot} {--confirm-digest= : Exact canonical preview SHA256} {--cutoff= : Explicit approved cutoff date} {--target-database= : Explicit destination database} {--actor= : Existing unit administrator user ID} {--allow-production : Additional deliberate production opt-in}';
    protected $description = 'Preview a normalized migration by default; commit only with all explicit safety gates';

    public function handle(MigrationImporter $importer): int
    {
        if ((int) $this->option('execute') + (int) $this->option('rehearse') + (int) $this->option('reconcile') > 1) return $this->invalid('migration_modes_are_mutually_exclusive');
        if ($this->option('rehearse') && $this->option('allow-production')) return $this->invalid('production_rehearsal_forbidden');
        $file = $this->argument('file');
        if (!is_string($file) || strpos($file, '://') !== false || !is_file($file) || !is_readable($file)) return $this->invalid('readable_local_file_required');
        $bytes = @file_get_contents($file, false, null, 0, 5242881);
        if ($bytes === false || strlen($bytes) > 5242880) return $this->invalid('unreadable_or_oversized_file');
        try {
            $data = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            if (!is_array($data) || array_diff(array_keys($data), ['rows','references']) || !isset($data['rows'],$data['references']) || !is_array($data['rows']) || !is_array($data['references'])) return $this->invalid('invalid_envelope');
            if (!$this->option('execute') && !$this->option('rehearse')) {
                $result = $this->option('reconcile') ? $importer->reconcile($data['rows'], $data['references']) : $importer->preview($data['rows'], $data['references']);
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return $result['valid'] ? 0 : 1;
            }
            $actor = $this->option('actor');
            if (!is_string($actor) || !ctype_digit($actor) || (int) $actor < 1 || (string) (int) $actor !== $actor) return $this->invalid('explicit_actor_required');
            $target = $this->option('target-database');
            if (!is_string($target) || $target === '' || $target !== ($data['references']['target_database'] ?? null)) return $this->invalid('explicit_target_confirmation_required');
            if (!is_string($this->option('confirm-digest')) || !is_string($this->option('cutoff'))) return $this->invalid('explicit_digest_and_cutoff_required');
            $result = $this->option('rehearse')
                ? $importer->rehearse($data['rows'], $data['references'], $this->option('confirm-digest'), $this->option('cutoff'), (int) $actor)
                : $importer->execute($data['rows'], $data['references'], $this->option('confirm-digest'), $this->option('cutoff'), (int) $actor, (bool) $this->option('allow-production'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        } catch (ValidationException $e) {
            return $this->invalid(array_values($e->errors())[0][0]);
        } catch (\Throwable $e) {
            // Do not expose SQL, source personal rows, paths, or exception data.
            return $this->invalid('migration_failed_no_partial_commit');
        }
    }

    private function invalid(string $code): int
    {
        $this->line(json_encode(['valid'=>false,'can_import'=>false,'writes'=>0,'issues'=>[['field'=>'migration','code'=>$code,'blocking'=>true]]], JSON_PRETTY_PRINT));
        return 1;
    }
}
