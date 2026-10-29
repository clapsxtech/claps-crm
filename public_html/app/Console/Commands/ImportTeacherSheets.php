<?php

namespace App\Console\Commands;

use App\Modules\Erp\Services\TeacherSheetImporter;
use Illuminate\Console\Command;

class ImportTeacherSheets extends Command
{
    protected $signature='erp:teacher-sheets-import {directory} {--unit=} {--actor=} {--execute} {--confirm-digest=} {--target-database=} {--allow-production}';
    protected $description='Preview or resume importing read-only teacher recruitment and registration snapshots';

    public function handle(TeacherSheetImporter $importer): int
    {
        $dir=$this->argument('directory');
        $preview=$importer->preview($dir);
        $this->line(json_encode($preview,JSON_PRETTY_PRINT));
        if (!$this->option('execute')) return self::SUCCESS;
        if (!hash_equals($preview['digest'],(string)$this->option('confirm-digest')) || !$this->option('unit') || !$this->option('actor')) { $this->error('Exact preview digest, unit and actor are required.');return self::FAILURE; }
        $target=(string)config('database.connections.'.config('database.default').'.database');
        if ($target!==$this->option('target-database') || (app()->environment('production') && !$this->option('allow-production'))) { $this->error('Exact database target and production opt-in are required.');return self::FAILURE; }
        try {
            $result=$importer->execute($dir,(int)$this->option('unit'),(int)$this->option('actor'),fn($file,$counts)=>$this->line($file.' processed; source rows='.$counts['source_rows'].'; existing='.$counts['already_imported']));
            $this->line(json_encode($result,JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Import stopped ('.class_basename($e).'). Completed chunks are retained. Rerun the same digest to resume.');
            return self::FAILURE;
        }
    }
}
