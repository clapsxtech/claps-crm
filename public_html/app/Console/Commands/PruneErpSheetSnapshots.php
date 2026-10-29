<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneErpSheetSnapshots extends Command
{
    protected $signature = 'erp:sheets-prune {--days=7}';
    protected $description = 'Remove old transition snapshots, retaining active copies and the latest review';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 1) { $this->error('Retention must be at least one day.'); return self::FAILURE; }
        $states = DB::table('erp_sheet_sources')->get();
        $keep = $states->pluck('active_run_id')->merge($states->pluck('latest_run_id'))->filter()->all();
        $runs = DB::table('erp_sheet_runs')->whereNotIn('id',$keep)->where('status','!=','running')->where('completed_at','<',now()->subDays($days))->pluck('id');
        foreach ($runs as $run) {
            do {
                $ids = DB::table('erp_sheet_records')->where('run_id',$run)->limit(100)->pluck('id')->all();
                if ($ids) DB::table('erp_sheet_records')->whereIn('id',$ids)->delete();
            } while ($ids);
            DB::table('erp_sheet_runs')->where('id',$run)->delete();
        }
        $this->info('Old snapshots removed: '.$runs->count());
        return self::SUCCESS;
    }
}
