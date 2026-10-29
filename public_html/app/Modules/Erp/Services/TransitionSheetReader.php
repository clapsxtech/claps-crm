<?php

namespace App\Modules\Erp\Services;

use Google\Client;
use Google\Service\{Drive, Sheets};
use RuntimeException;

/** No write API or outbound queue is used by this boundary. */
class TransitionSheetReader
{
    public function read(array $source, callable $heartbeat): array
    {
        $credentials = config('sheet_transition.credentials_path');
        if (!is_string($credentials) || !is_readable($credentials)) throw new RuntimeException('credentials_unavailable');
        try {
            $client = $this->client($credentials);
            $sheets = new Sheets($client); $drive = new Drive($client);
            $id = $source['spreadsheet_id'];
            $version = $this->version($drive, $id);
            $metadata = $sheets->spreadsheets->get($id, ['fields'=>'sheets.properties']);
            $tab = null;
            foreach ($metadata->getSheets() as $sheet) {
                if ((int) $sheet->getProperties()->getSheetId() === $source['gid']) $tab = $sheet->getProperties();
            }
            if (!$tab || $tab->getSheetType() !== 'GRID') throw new RuntimeException('source_tab_missing');
            $rows = (int) $tab->getGridProperties()->getRowCount();
            // Fail instead of silently importing a truncated source.
            if ($rows > config('sheet_transition.max_grid_rows') || $rows < $source['first_row']) throw new RuntimeException('source_grid_limit');
            $prefix = "'".str_replace("'", "''", $tab->getTitle())."'!";
            $header = $sheets->spreadsheets_values->get($id, $prefix.'A1:'.$source['last_column'].($source['first_row']-1), ['valueRenderOption'=>'FORMATTED_VALUE'])->getValues() ?? [];
            foreach ($source['headers'] as $cell=>$expected) {
                preg_match('/^([A-Z]+)([0-9]+)$/', $cell, $parts);
                $column = 0;
                foreach (str_split($parts[1]) as $letter) $column = $column*26 + ord($letter)-64;
                if (trim((string) ($header[(int)$parts[2]-1][$column-1] ?? '')) !== $expected) throw new RuntimeException('header_changed');
            }
            $values = []; $bytes = 0;
            for ($start = $source['first_row']; $start <= $rows; $start += 500) {
                $heartbeat();
                // A dedicated service account has a per-minute read quota; leave room for metadata reads.
                $this->pace();
                $end = min($start+499, $rows);
                $page = $sheets->spreadsheets_values->get($id, $prefix.'A'.$start.':'.$source['last_column'].$end, ['valueRenderOption'=>'FORMATTED_VALUE'])->getValues() ?? [];
                $bytes += strlen(json_encode($page, JSON_THROW_ON_ERROR));
                if ($bytes > config('sheet_transition.max_payload_bytes')) throw new RuntimeException('source_payload_limit');
                foreach ($page as $offset=>$row) {
                    if (array_filter($row, fn($v)=>$v !== null && trim((string)$v) !== '')) $values[$start+$offset] = $row;
                }
                if (count($values) > config('sheet_transition.max_populated_rows')) throw new RuntimeException('source_payload_limit');
            }
            $heartbeat();
            if ($version !== $this->version($drive, $id)) throw new RuntimeException('source_changed_during_read');
            return ['version'=>$version, 'rows'=>$values];
        } catch (\Throwable $e) {
            // Never return upstream response bodies, credentials, or private cell contents.
            $safe = ['source_tab_missing','source_grid_limit','source_payload_limit','header_changed','source_changed_during_read','lease_lost'];
            throw new RuntimeException(in_array($e->getMessage(), $safe, true) ? $e->getMessage() : 'source_read_failed');
        }
    }

    private function version(Drive $drive, string $id): string
    {
        $version = (string) $drive->files->get($id, ['fields'=>'version', 'supportsAllDrives'=>true])->getVersion();
        if ($version === '') throw new RuntimeException('source_read_failed');
        return $version;
    }

    protected function client(string $credentials): Client
    {
        $client = new Client(['retry'=>['retries'=>3,'initial_delay'=>1,'max_delay'=>8], 'retry_map'=>[429=>-1,500=>-1,503=>-1,'rateLimitExceeded'=>-1,'userRateLimitExceeded'=>-1]]);
        $client->setApplicationName('ClapsLearn transition reader');
        $client->setAuthConfig($credentials);
        $client->setScopes([Sheets::SPREADSHEETS_READONLY, Drive::DRIVE_METADATA_READONLY]);
        $client->setHttpClient(new \GuzzleHttp\Client(['timeout'=>45, 'connect_timeout'=>10,'progress'=>static function ($total, $downloaded) {
            if ($downloaded > 4 * 1024 * 1024) throw new RuntimeException('source_payload_limit');
        }]));
        return $client;
    }

    protected function pace(): void { usleep(1500000); }
}
