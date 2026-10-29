<?php
namespace App\Infrastructure\Cloudflare;

use PDO;
use RuntimeException;
use Throwable;

/** A disposable SQLite working copy. D1 is the authoritative commit store. */
class D1Pdo extends PDO
{
    private $lock;
    private array $journal = [];
    private array $savepoints = [];
    private int $revision;
    private bool $unusable = false;

    public function __construct(private Gateway $gateway, string $path)
    {
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        $this->lock = fopen($path.'.lock', 'c');
        if (!$this->lock || !flock($this->lock, LOCK_EX)) throw new RuntimeException('Cannot lock D1 working copy.');
        chmod($path.'.lock', 0600);
        $state = $gateway->call('state');
        $local = null;
        if (is_file($path)) {
            try {
                $probe = new PDO('sqlite:'.$path);
                $local = $probe->query('SELECT revision FROM erp_d1_local')->fetchColumn();
                $probe = null;
            } catch (Throwable) { $local = null; }
        }
        if ($local === null || (int) $local !== (int) $state['revision']) $this->restore($path);
        parent::__construct('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        parent::exec('PRAGMA foreign_keys=ON');
        $this->revision = (int) parent::query('SELECT revision FROM erp_d1_local')->fetchColumn();
    }

    private function restore(string $path): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $snapshot = $this->gateway->call('schema');
            $temporary = $path.'.'.bin2hex(random_bytes(8));
            $db = new PDO('sqlite:'.$temporary, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            chmod($temporary, 0600);
            try {
                $db->exec('PRAGMA foreign_keys=OFF');
                $db->beginTransaction();
                foreach ($snapshot['schema'] as $object) if ($object['type'] === 'table') $db->exec($object['sql']);
                $tables = array_values(array_filter($snapshot['schema'], fn($object) => $object['type'] === 'table'));
                $pending = array_fill_keys(array_column($tables, 'name'), -1);
                $inserts = [];
                // Batch continuation pages too: fetching each 200-row page over
                // a separate HTTP call made imported databases miss readiness.
                while ($pending) {
                    foreach (array_chunk(array_keys($pending), 8) as $names) {
                        $cursors = array_intersect_key($pending, array_flip($names));
                        $pages = $this->gateway->call('rows_batch', ['tables'=>$names, 'cursors'=>$cursors, 'limit'=>2000])['pages'];
                        foreach ($names as $name) {
                            $page = $pages[$name];
                            $previous = $pending[$name];
                            foreach ($page['rows'] as $row) {
                                $cursor = (int)$row['_erp_d1_rowid'];
                                if ($cursor <= $pending[$name]) throw new RuntimeException('D1 snapshot cursor did not advance.');
                                $pending[$name] = $cursor;
                                unset($row['_erp_d1_rowid']);
                                if (!isset($inserts[$name])) {
                                    $columns = implode(',', array_map(self::identifier(...), array_keys($row)));
                                    $inserts[$name] = $db->prepare('INSERT INTO '.self::identifier($name).' ('.$columns.') VALUES ('.implode(',', array_fill(0, count($row), '?')).')');
                                }
                                $inserts[$name]->execute(array_values($row));
                            }
                            if (!$page['more']) unset($pending[$name]);
                            elseif ($pending[$name] === $previous) throw new RuntimeException('D1 snapshot returned an empty continuation page.');
                        }
                    }
                }
                $inserts = [];
                if (isset($snapshot['sequences'])) {
                    $db->exec('DELETE FROM sqlite_sequence');
                    $insert = $db->prepare('INSERT INTO sqlite_sequence(name,seq) VALUES (?,?)');
                    foreach ($snapshot['sequences'] as $sequence) $insert->execute([$sequence['name'], $sequence['seq']]);
                }
                foreach ($snapshot['schema'] as $object) if ($object['type'] !== 'table') $db->exec($object['sql']);
                $end = $this->gateway->call('state');
                if ((int) $end['revision'] !== (int) $snapshot['revision']) {
                    $db->rollBack(); $db = null; unlink($temporary); continue;
                }
                $db->exec('CREATE TABLE erp_d1_local (revision INTEGER NOT NULL)');
                $db->prepare('INSERT INTO erp_d1_local VALUES (?)')->execute([$snapshot['revision']]);
                if ($db->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('D1 snapshot violates foreign keys.');
                $db->commit(); $db = null;
                if (!rename($temporary, $path)) throw new RuntimeException('Cannot install D1 working copy.');
                return;
            } catch (Throwable $error) {
                $db = null;
                if (is_file($temporary)) unlink($temporary);
                throw $error;
            }
        }
        throw new RuntimeException('D1 changed during snapshot; retry the request.');
    }

    public static function identifier(string $value): string { return '"'.str_replace('"', '""', $value).'"'; }

    public function beginTransaction(): bool
    {
        $this->assertUsable();
        $this->journal = []; $this->savepoints = [];
        return parent::beginTransaction();
    }

    public function assertUsable(): void
    {
        if ($this->unusable) throw new RuntimeException('D1 working copy requires a new request.');
    }

    public function record(string $sql, array $bindings): void
    {
        $this->journal[] = ['sql' => $sql, 'params' => $bindings];
    }

    public function exec(string $statement): int|false
    {
        if (preg_match('/^SAVEPOINT (\w+)$/i', $statement, $m)) $this->savepoints[$m[1]] = count($this->journal);
        if (preg_match('/^ROLLBACK TO SAVEPOINT (\w+)$/i', $statement, $m)) {
            if (!isset($this->savepoints[$m[1]])) throw new RuntimeException('Unknown D1 savepoint.');
            $this->journal = array_slice($this->journal, 0, $this->savepoints[$m[1]]);
        }
        return parent::exec($statement);
    }

    public function commit(): bool
    {
        if ($this->journal) {
            $request = ['id' => bin2hex(random_bytes(24)), 'revision' => $this->revision, 'statements' => $this->journal];
            try {
                // The same ID is used for retry: a lost response must never replay a payment.
                try { $result = $this->gateway->call('commit', $request); }
                catch (Throwable) { $result = $this->gateway->call('commit', $request); }
                $revision = (int) $result['revision'];
                parent::prepare('UPDATE erp_d1_local SET revision=?')->execute([$revision]);
                $this->revision = $revision;
            } catch (Throwable $error) {
                if (parent::inTransaction()) parent::rollBack();
                $this->journal = []; $this->unusable = true;
                throw new UnconfirmedCommit('D1 commit was not confirmed. Start a new request; do not assume this operation failed.', 0, $error);
            }
        }
        $this->journal = []; $this->savepoints = [];
        try { return parent::commit(); }
        catch (Throwable $error) {
            $this->unusable = true;
            if (parent::inTransaction()) parent::rollBack();
            throw new UnconfirmedCommit('D1 committed but the working copy could not be updated. Start a new request.', 0, $error);
        }
    }

    public function rollBack(): bool
    {
        $this->journal = []; $this->savepoints = [];
        return parent::inTransaction() ? parent::rollBack() : true;
    }
}
