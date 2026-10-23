<?php

namespace App\Console\Commands;

use App\Modules\Integrations\Services\DeliverOutbox;
use Illuminate\Console\Command;

class DeliverIntegrationOutbox extends Command
{
    protected $signature = 'integration:deliver-outbox {--limit=100 : Maximum records per run}';
    protected $description = 'Deliver pending Sheets events once; quarantine ambiguous results for reconciliation';

    public function handle(DeliverOutbox $delivery)
    {
        if (!config('integrations.sheets.enabled', false)) {
            $this->warn('Sheets delivery is disabled. No events were dispatched.');
            return 0;
        }
        $counts = $delivery->run((int) $this->option('limit'));
        $this->info('Sent: '.$counts['sent'].'; pending: '.$counts['pending'].'; uncertain: '.$counts['uncertain']);

        return 0;
    }
}
