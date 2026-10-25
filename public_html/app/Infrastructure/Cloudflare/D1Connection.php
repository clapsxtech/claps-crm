<?php
namespace App\Infrastructure\Cloudflare;

use Closure;
use Illuminate\Database\SQLiteConnection;
use RuntimeException;
use Throwable;

class D1Connection extends SQLiteConnection
{
    protected function executeBeginTransactionStatement() { $this->getPdo()->beginTransaction(); }

    protected function run($query, $bindings, Closure $callback)
    {
        // Production schema changes are applied by the D1 migration tool, never a web request.
        $mutation = preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $query) === 1;
        if (!$mutation && !preg_match('/^\s*(SELECT|PRAGMA|EXPLAIN)\b/i', $query)) {
            throw new RuntimeException('Unsupported D1 statement; schema changes require the deployment migration tool.');
        }
        if ($mutation && preg_match('/\b(random|randomblob|current_timestamp|current_date|current_time|last_insert_rowid)\b|\b(datetime|date|time|strftime)\s*\(/i', $query)) {
            throw new RuntimeException('D1 mutations must bind deterministic values.');
        }
        $pdo = $this->getPdo();
        $pdo->assertUsable();
        $automatic = $mutation && !$pdo->inTransaction();
        if ($automatic) $pdo->beginTransaction();
        try {
            $result = parent::run($query, $bindings, $callback);
            if ($mutation) $pdo->record($query, $this->prepareBindings($bindings));
            if ($automatic) $pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($automatic) $pdo->rollBack();
            throw $error;
        }
    }
}
