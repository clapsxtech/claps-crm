<?php
namespace App\Modules\Integrations\Services;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Validation\ValidationException;

/** Read-only integration boundary. Never returns transport errors containing credentials. */
class SheetSnapshotReader
{
    public function read(string $range): array
    {
        abort_unless(config('integrations.sheets.enabled'), 503, 'Spreadsheet synchronization is not enabled.');
        $spreadsheet = (string) config('integrations.sheets.spreadsheet_id');
        $credentials = (string) config('integrations.sheets.credentials_path');
        abort_unless($spreadsheet !== '' && $credentials !== '' && is_readable($credentials), 503, 'Spreadsheet synchronization needs administrator configuration.');
        try {
            $client = new Client();
            $client->setApplicationName('Clapslearn');
            $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
            $client->setAuthConfig($credentials);
            return (new Sheets($client))->spreadsheets_values->get($spreadsheet, $range)->getValues() ?? [];
        } catch (\Throwable $exception) {
            abort(502, 'The spreadsheet could not be read. Existing data was not changed.');
        }
    }

    /** Skip only fully blank rows; a partial row must never silently disappear. */
    public static function rows(array $values, int $minimumColumns): array
    {
        $rows = [];
        foreach (array_slice($values, 1) as $row) {
            if (!is_array($row)) self::invalid();
            $row = array_map(function ($value) {
                if (!is_scalar($value) && $value !== null) self::invalid();
                $value = trim((string) $value);
                if (mb_strlen($value) > 255) self::invalid();
                return $value;
            }, array_values($row));
            if (count(array_filter($row, fn ($cell) => $cell !== '')) === 0) continue;
            if (count($row) < $minimumColumns) self::invalid();
            $rows[] = $row;
        }
        if (!$rows) self::invalid();
        return $rows;
    }

    public static function requireFields(array $record, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($record[$field]) || trim((string) $record[$field]) === '') self::invalid();
        }
    }

    public static function decimal(string $value, bool $signed = false): string
    {
        $pattern = $signed ? '/^-?\d{1,8}(?:\.\d{1,2})?$/D' : '/^\d{1,8}(?:\.\d{1,2})?$/D';
        if (!preg_match($pattern, $value)) self::invalid();
        return bcadd($value, '0', 2);
    }

    public static function date(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $value);
        if (!$date || $date->format('d/m/Y') !== $value) self::invalid();
        return $date->format('Y-m-d');
    }

    public static function invalid(): void
    {
        throw ValidationException::withMessages(['sheet' => 'The spreadsheet is empty, malformed, or contains conflicting identities. Existing data was not changed.']);
    }
}
