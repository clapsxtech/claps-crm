<?php

namespace App\Modules\Integrations\Transports;

use App\Modules\Integrations\Contracts\SheetsTransport;
use App\Modules\Integrations\Exceptions\DeliveryNotDispatched;
use App\Modules\Integrations\Exceptions\DeliveryUncertain;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use GuzzleHttp\Client as HttpClient;
use Throwable;

class GoogleSheetsTransport implements SheetsTransport
{
    public function append(array $payload): ?string
    {
        $id = config('integrations.sheets.spreadsheet_id');
        $credentials = config('integrations.sheets.credentials_path');
        if (!config('integrations.sheets.enabled', false) || !$id || !$credentials || !is_readable($credentials)) {
            throw new DeliveryNotDispatched('integration_not_configured');
        }
        if (!in_array($payload['schema'] ?? null, ['marklist.v1', 'performance.v1'], true)) {
            throw new DeliveryNotDispatched('unsupported_payload_schema');
        }

        try {
            $client = new Client();
            $client->setApplicationName('ClapsLearn');
            $client->setScopes([Sheets::SPREADSHEETS]);
            $client->setAuthConfig($credentials);
            // values.append is not idempotent. Disable SDK retry middleware.
            $client->setConfig('retry', ['retries' => 0]);
            $client->setHttpClient(new HttpClient(['connect_timeout' => 10, 'timeout' => 30]));
            $service = new Sheets($client);
            $body = new ValueRange(['values' => $payload['values']]);
        } catch (Throwable $exception) {
            // No remote append has been attempted. Deliberately do not retain
            // SDK exception text, request data, credentials, or exception chains.
            throw new DeliveryNotDispatched('client_initialization_failed');
        }

        try {
            $response = $service->spreadsheets_values->append($id, $payload['range'], $body, ['valueInputOption' => 'RAW']);
            $updates = $response->getUpdates();

            return $updates ? $updates->getUpdatedRange() : null;
        } catch (Throwable $exception) {
            // A timeout/error is not proof that append did not commit remotely.
            throw new DeliveryUncertain('remote_append_outcome_unknown');
        }
    }
}
