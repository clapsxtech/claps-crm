<?php

namespace App\Console\Commands;

use App\Modules\Erp\Services\SheetTransition;
use Illuminate\Console\Command;

class RefreshErpSheets extends Command
{
    protected $signature = 'erp:sheets-refresh {source=all} {--publish : Publish validated snapshots; otherwise preview without writes} {--approve-removals= : Exact preview digest authorizing removed source identities}';
    protected $description = 'Read the reviewed Google Sheet sources into the isolated transition workspace';

    public function handle(SheetTransition $transition): int
    {
        $keys = $this->argument('source') === 'all' ? array_keys(config('sheet_transition.sources')) : [$this->argument('source')];
        if ($this->option('approve-removals') && count($keys) !== 1) { $this->error('Approve removals for one source at a time.'); return self::FAILURE; }
        $failed = false;
        foreach ($keys as $key) {
            try {
                $result = $transition->refresh($key,(bool)$this->option('publish'),$this->option('approve-removals'));
                $this->line(json_encode($result, JSON_THROW_ON_ERROR));
                $failed = $failed || $result['status'] !== 'ready';
            } catch (\RuntimeException $e) {
                // Service exceptions are fixed codes, never Google response bodies.
                $this->error($key.': '.$e->getMessage()); $failed = true;
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
