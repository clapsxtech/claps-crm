<?php

namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Operator-only, read-only indicators. Never include payloads, credentials or exception text. */
class OperationalHealth
{
    public function inspect(int $staleMinutes = 15): array
    {
        $checks = [];
        try {
            DB::connection()->select('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'checks' => ['database' => ['status' => 'error', 'code' => 'database_unavailable']]];
        }

        $this->check($checks, 'schema', function () {
            $required = array_map('basename', glob(database_path('migrations/*.php')) ?: []);
            $required = array_map(function ($name) { return substr($name, 0, -4); }, $required);
            $applied = DB::table('migrations')->pluck('migration')->all();
            $pending = count(array_diff($required, $applied));
            $missing = 0;
            foreach (['erp_units', 'erp_students', 'erp_ledger', 'erp_audit', 'integration_outbox', 'failed_jobs'] as $table) {
                if (!Schema::hasTable($table)) ++$missing;
            }
            return ['status' => $pending || $missing ? 'error' : 'ok', 'pending_migrations' => $pending, 'missing_required_tables' => $missing];
        });
        $this->check($checks, 'failed_jobs', function () {
            $count = DB::table('failed_jobs')->count();
            return ['status' => $count ? 'warning' : 'ok', 'count' => $count];
        });
        $this->check($checks, 'outbox', function () use ($staleMinutes) {
            $counts = DB::table('integration_outbox')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(function ($count) { return (int) $count; })->all();
            $stale = DB::table('integration_outbox')->where('status', 'sending')->where(function ($query) {
                $query->whereNull('claimed_at')->orWhere('claimed_at', '<=', now()->subSeconds(max(60, (int) config('integrations.sheets.claim_timeout_seconds', 300))));
            })->count();
            $oldPending = DB::table('integration_outbox')->where('status', 'pending')->where('created_at', '<=', now()->subMinutes($staleMinutes))->count();
            $unknown = array_sum(array_diff_key($counts, array_flip(['pending', 'sending', 'sent', 'uncertain'])));
            $uncertain = $counts['uncertain'] ?? 0;
            return ['status' => $stale || $oldPending || $unknown || $uncertain ? 'warning' : 'ok',
                'delivery_enabled' => (bool) config('integrations.sheets.enabled', false),
                'pending' => $counts['pending'] ?? 0, 'sending' => $counts['sending'] ?? 0,
                'uncertain' => $uncertain, 'stale_claims' => $stale, 'old_pending' => $oldPending,
                'unknown_status' => $unknown, 'pending_age_threshold_minutes' => $staleMinutes];
        });
        $this->check($checks, 'audit', function () {
            return ['status' => 'ok', 'events_last_24_hours' => DB::table('erp_audit')->where('created_at', '>=', now()->subDay())->count(),
                'latest_event_at' => DB::table('erp_audit')->max('created_at')];
        });
        $statuses = array_column($checks, 'status');
        return ['status' => in_array('error', $statuses, true) ? 'error' : (in_array('warning', $statuses, true) ? 'warning' : 'ok'), 'checks' => $checks];
    }

    private function check(array &$checks, string $name, callable $inspect): void
    {
        try {
            $checks[$name] = $inspect();
        } catch (Throwable $e) {
            $checks[$name] = ['status' => 'error', 'code' => 'check_unavailable'];
        }
    }
}
