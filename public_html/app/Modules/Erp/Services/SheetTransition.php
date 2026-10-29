<?php

namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SheetTransition
{
    public function __construct(private TransitionSheetReader $reader, private TransitionSheetMapper $mapper) {}

    public function refresh(string $key, bool $publish = false, ?string $approvedRemovals = null): array
    {
        if (!config('sheet_transition.enabled')) throw new RuntimeException('transition_disabled');
        $source = config('sheet_transition.sources.'.$key);
        if (!is_array($source)) throw new RuntimeException('unknown_source');
        // Source configuration and team mappings form part of the reviewable contract.
        $contract = hash('sha256', json_encode([$source,config('sheet_transition.band_units')], JSON_THROW_ON_ERROR));
        $run = (string) Str::uuid();
        if ($publish) $this->claim($key, $run, $contract);
        try {
            $dependencies = $this->dependencies();
            $snapshot = $this->reader->read($source, function () use ($publish,$key,$run) {
                if ($publish) $this->heartbeat($key,$run);
            });
            $records = $this->mapper->map($key, $source, $snapshot['rows']);
            if ($dependencies !== $this->dependencies()) throw new RuntimeException('identity_sources_changed');
            $issues = count(array_filter($records, fn($r)=>$r['issue'] !== null));
            $digest = hash('sha256', json_encode([$contract,$records], JSON_THROW_ON_ERROR));
            $reason = !$records ? 'empty_source' : ($issues ? 'rows_need_review' : null);
            $previous = DB::table('erp_sheet_sources')->where('source_key',$key)->value('active_run_id');
            $removed = [];
            if ($previous) {
                $old = DB::table('erp_sheet_records')->where('run_id',$previous)->pluck('source_identity')->all();
                $removed = array_diff($old, array_column($records,'source_identity'));
                // Deletions need a digest-specific operator review; absence is never an implicit delete.
                if ($removed && !hash_equals($digest, $approvedRemovals ?? '')) $reason ??= 'source_identities_removed';
            }
            $result = ['source'=>$key,'mode'=>$publish?'refresh':'preview','status'=>$reason?'review':'ready','reason'=>$reason,'rows'=>count($records),'issues'=>$issues,'removed'=>count($removed),'digest'=>$digest];
            if (!$publish) return $result;
            $unchanged = !$reason && $previous && DB::table('erp_sheet_runs')->where('id',$previous)->value('digest') === $digest;
            // Small batches stay within D1 statement/parameter limits. Incomplete runs stay invisible.
            foreach ($this->batches($unchanged ? [] : $records) as $chunk) {
                $this->heartbeat($key,$run);
                DB::table('erp_sheet_records')->insert(array_map(fn($row)=>['run_id'=>$run]+$row,$chunk));
            }
            DB::transaction(function () use ($key,$run,$snapshot,$result,$dependencies,$unchanged,$previous) {
                if ($dependencies !== $this->dependencies()) throw new RuntimeException('identity_sources_changed');
                $state = ['last_status'=>$result['status'],'lease_token'=>null,'lease_until'=>null];
                if ($result['status'] === 'ready') $state += ['active_run_id'=>$unchanged ? $previous : $run,'last_success_at'=>now()];
                $updated = DB::table('erp_sheet_sources')->where('source_key',$key)->where('lease_token',$run)->where('lease_until','>',now())->update($state);
                if ($updated !== 1) throw new RuntimeException('lease_lost');
                DB::table('erp_sheet_runs')->where('id',$run)->update(['status'=>$unchanged?'unchanged':$result['status'],'reason'=>$result['reason'],'source_version'=>$snapshot['version'],'digest'=>$result['digest'],'row_count'=>$result['rows'],'issue_count'=>$result['issues'],'completed_at'=>now()]);
            });
            return $result + ['run_id'=>$run];
        } catch (\Throwable $e) {
            $safe = ['credentials_unavailable','source_tab_missing','source_grid_limit','source_payload_limit','header_changed','source_changed_during_read','source_read_failed','lease_lost','identity_sources_changed'];
            $reason = in_array($e->getMessage(),$safe,true) ? $e->getMessage() : 'refresh_failed';
            if ($publish) {
                DB::table('erp_sheet_sources')->where('source_key',$key)->where('lease_token',$run)->update(['last_status'=>'failed','lease_token'=>null,'lease_until'=>null]);
                DB::table('erp_sheet_runs')->where('id',$run)->update(['status'=>'failed','reason'=>$reason,'completed_at'=>now()]);
            }
            throw new RuntimeException($reason);
        }
    }

    private function claim(string $key, string $run, string $contract): void
    {
        DB::table('erp_sheet_sources')->insertOrIgnore(['source_key'=>$key]);
        DB::transaction(function () use ($key,$run,$contract) {
            $claimed = DB::table('erp_sheet_sources')->where('source_key',$key)->where(function ($q) {
                $q->whereNull('lease_token')->orWhere('lease_until','<=',now());
            })->update(['latest_run_id'=>$run,'lease_token'=>$run,'lease_until'=>now()->addSeconds(config('sheet_transition.lease_seconds')),'last_attempt_at'=>now(),'last_status'=>'running']);
            if ($claimed !== 1) throw new RuntimeException('refresh_already_running');
            // Mark abandoned work without ever exposing its staged records.
            DB::table('erp_sheet_runs')->where('source_key',$key)->where('status','running')->update(['status'=>'failed','reason'=>'lease_expired','completed_at'=>now()]);
            DB::table('erp_sheet_runs')->insert(['id'=>$run,'source_key'=>$key,'status'=>'running','contract_digest'=>$contract,'created_at'=>now()]);
        });
    }

    private function heartbeat(string $key, string $run): void
    {
        // Increment even within the same second: MySQL reports changed rows, not matched rows.
        $updated = DB::table('erp_sheet_sources')->where('source_key',$key)->where('lease_token',$run)->where('lease_until','>',now())->update(['lease_until'=>now()->addSeconds(config('sheet_transition.lease_seconds')),'lease_tick'=>DB::raw('lease_tick + 1')]);
        if ($updated !== 1) throw new RuntimeException('lease_lost');
    }

    private function dependencies(): array
    {
        return [
            'sources'=>DB::table('erp_sheet_sources')->whereIn('source_key',['students','teachers'])->orderBy('source_key')->pluck('active_run_id','source_key')->all(),
            'people'=>hash('sha256',json_encode([
                DB::table('erp_students')->orderBy('id')->get(['id','code','name','unit_id']),
                DB::table('erp_teachers')->orderBy('id')->get(['id','code','name']),
                DB::table('erp_units')->orderBy('id')->get(['id','archived_at']),
            ],JSON_THROW_ON_ERROR)),
        ];
    }

    private function batches(array $records): \Generator
    {
        $batch = []; $bytes = 0;
        foreach ($records as $row) {
            $size = strlen($row['payload']) + 500;
            if ($batch && ($bytes+$size > 24000 || count($batch) >= 20)) {
                yield $batch; $batch = []; $bytes = 0;
            }
            $batch[] = $row; $bytes += $size;
        }
        if ($batch) yield $batch;
    }
}
