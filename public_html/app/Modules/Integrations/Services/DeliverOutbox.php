<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Contracts\SheetsTransport;
use App\Modules\Integrations\Exceptions\DeliveryNotDispatched;
use App\Modules\Integrations\Models\IntegrationOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DeliverOutbox
{
    private $transport;

    public function __construct(SheetsTransport $transport)
    {
        $this->transport = $transport;
    }

    public function run(int $limit = 100): array
    {
        $counts = ['sent' => 0, 'pending' => 0, 'uncertain' => 0];
        // A process can die after append and before recording success. A stale
        // in-flight record must be quarantined, never reset to pending.
        $counts['uncertain'] += IntegrationOutbox::where('status', 'sending')
            ->where('claimed_at', '<=', now()->subSeconds(max(60, (int) config('integrations.sheets.claim_timeout_seconds', 300))))
            ->update(['status' => 'uncertain', 'last_error_code' => 'stale_claim_outcome_unknown', 'updated_at' => now()]);
        $ids = IntegrationOutbox::where('status', 'pending')
            ->where(function ($query) { $query->whereNull('available_at')->orWhere('available_at', '<=', now()); })
            ->orderBy('id')->limit(max(1, min($limit, 1000)))->pluck('id');

        foreach ($ids as $id) {
            $status = $this->deliver((int) $id);
            if (isset($counts[$status])) {
                ++$counts[$status];
            }
        }

        return $counts;
    }

    public function deliver(int $id): string
    {
        $token = (string) Str::uuid();
        $claimed = IntegrationOutbox::whereKey($id)->where('status', 'pending')
            ->where(function ($query) { $query->whereNull('available_at')->orWhere('available_at', '<=', now()); })
            ->update([
                'status' => 'sending', 'claim_token' => $token, 'claimed_at' => now(),
                'attempts' => DB::raw('attempts + 1'), 'updated_at' => now(),
            ]);
        if (!$claimed) {
            return 'skipped';
        }
        $event = IntegrationOutbox::findOrFail($id);

        try {
            $receipt = $this->transport->append($event->payload);
            $status = 'sent';
            $changes = ['status' => 'sent', 'sent_at' => now(), 'provider_receipt' => $receipt, 'last_error_code' => null];
        } catch (DeliveryNotDispatched $exception) {
            $status = 'pending';
            $changes = [
                'status' => 'pending', 'claim_token' => null, 'claimed_at' => null,
                'available_at' => now()->addSeconds(max(30, (int) config('integrations.sheets.retry_delay_seconds', 300))),
                'last_error_code' => 'not_dispatched',
            ];
        } catch (Throwable $exception) {
            $status = 'uncertain';
            $changes = ['status' => 'uncertain', 'last_error_code' => 'remote_append_outcome_unknown'];
        }

        // A reconciler/stale-claim sweep may have quarantined this row while
        // network I/O ran. Never overwrite that decision with a stale token.
        $updated = IntegrationOutbox::whereKey($id)->where('status', 'sending')->where('claim_token', $token)
            ->update(array_merge($changes, ['updated_at' => now()]));

        return $updated ? $status : 'uncertain';
    }
}
