<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Exceptions\SubmissionConflict;
use App\Modules\Integrations\Models\IntegrationOutbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SubmissionWriter
{
    /**
     * The caller must authenticate BEFORE invoking this method. $input must be
     * an explicit credential-free allowlist, never Request::all(). The unique
     * outbox key reserves the request before the business record is written.
     */
    public function submit(string $key, string $eventType, array $input, callable $create): array
    {
        $key = strtolower($key);
        $hash = hash('sha256', json_encode([$eventType, $input], JSON_THROW_ON_ERROR));
        $existing = IntegrationOutbox::where('submission_key', $key)->first();
        if ($existing) {
            return $this->replay($existing, $hash);
        }

        try {
            return DB::transaction(function () use ($key, $eventType, $hash, $create) {
                $event = IntegrationOutbox::create([
                    'submission_key' => $key,
                    'payload_hash' => $hash,
                    'event_type' => $eventType,
                    'payload' => [],
                    'status' => 'pending',
                    'available_at' => now(),
                ]);
                [$record, $payload] = $create();
                $event->update(['aggregate_id' => $record->getKey(), 'payload' => $payload]);

                return ['id' => $record->getKey(), 'replayed' => false];
            });
        } catch (QueryException $exception) {
            // A concurrent insert can lose the unique-key race. The failed
            // transaction rolls back first; read only the winner's committed row.
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                $existing = IntegrationOutbox::where('submission_key', $key)->first();
                if ($existing) {
                    return $this->replay($existing, $hash);
                }
            }
            throw $exception;
        }
    }

    private function replay(IntegrationOutbox $event, string $hash): array
    {
        if (!hash_equals($event->payload_hash, $hash)) {
            throw new SubmissionConflict();
        }

        return ['id' => $event->aggregate_id, 'replayed' => true];
    }
}
