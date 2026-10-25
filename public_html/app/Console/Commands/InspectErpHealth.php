<?php

namespace App\Console\Commands;

use App\Modules\Erp\Services\OperationalHealth;
use Illuminate\Console\Command;

class InspectErpHealth extends Command
{
    protected $signature = 'erp:health {--stale-minutes=15 : Pending outbox age warning threshold (1-10080)}';
    protected $description = 'Read-only operational indicators as redacted JSON; no delivery, alerts or repairs';

    public function handle(OperationalHealth $health)
    {
        $minutes = filter_var($this->option('stale-minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10080]]);
        if ($minutes === false) {
            $this->line(json_encode(['status' => 'error', 'code' => 'invalid_stale_minutes']));
            return 2;
        }
        $report = $health->inspect($minutes);
        $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $report['status'] === 'ok' ? 0 : ($report['status'] === 'warning' ? 1 : 2);
    }
}
