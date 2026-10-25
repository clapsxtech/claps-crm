<?php
/** Synthetic SQLite recovery rehearsal. Accepts no paths, credentials or real backup inputs. */
if (PHP_SAPI !== 'cli' || $argc !== 1) {
    fwrite(STDERR, "Usage: php tests/Integration/erp-recovery-drill.php (no arguments)\n");
    exit(2);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use App\Modules\Erp\Services\Finance;

$directory = sys_get_temp_dir().'/clapslearn-synthetic-recovery-'.bin2hex(random_bytes(16));
if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot create private rehearsal directory.');
$source = $directory.'/source.sqlite';
$backup = $directory.'/backup.sqlite';
$restored = $directory.'/restored.sqlite';
$checks = [];
function requireCheck(bool $value, string $name): void {
    global $checks;
    if (!$value) throw new RuntimeException('Rehearsal check failed: '.$name);
    $checks[] = $name;
}
function databaseDigest(PDO $pdo): string {
    $tables = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $data = [$pdo->query("SELECT type, name, tbl_name, sql FROM sqlite_master ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC)];
    foreach ($tables as $table) {
        $quoted = '"'.str_replace('"', '""', $table['name']).'"';
        $data[] = [$table, $pdo->query('SELECT * FROM '.$quoted.' ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC)];
    }
    return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
}
function restoreVerified(string $backup, string $expectedHash, string $destination): void {
    // Read and verify the same bytes we write; refuse to replace any existing destination.
    $bytes = file_get_contents($backup);
    if ($bytes === false || !hash_equals($expectedHash, hash('sha256', $bytes))) throw new RuntimeException('Backup integrity mismatch.');
    $handle = @fopen($destination, 'x');
    if (!$handle) throw new RuntimeException('Restore destination must be new.');
    try {
        if (fwrite($handle, $bytes) !== strlen($bytes)) throw new RuntimeException('Incomplete restore write.');
    } finally { fclose($handle); }
}
try {
    // Ignore local .env and cached configuration, and select only this new disposable SQLite file.
    foreach (['APP_ENV'=>'testing', 'APP_CONFIG_CACHE'=>$directory.'/config.php', 'APP_SERVICES_CACHE'=>$directory.'/services.php',
        'APP_PACKAGES_CACHE'=>$directory.'/packages.php', 'APP_ROUTES_CACHE'=>$directory.'/routes.php',
        'DB_CONNECTION'=>'sqlite', 'DB_DATABASE'=>$source, 'DB_URL'=>'', 'DATABASE_URL'=>'',
        'CACHE_DRIVER'=>'array', 'CACHE_STORE'=>'array', 'SESSION_DRIVER'=>'array', 'QUEUE_CONNECTION'=>'sync',
        'MAIL_MAILER'=>'array', 'LOG_CHANNEL'=>'stderr', 'SHEETS_SYNC_ENABLED'=>'false', 'GOOGLE_CLIENT_SECRET'=>''] as $key=>$value) {
        putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value;
    }
    touch($source);
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->useEnvironmentPath($directory);
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    config(['database.default'=>'sqlite', 'database.connections.sqlite'=>['driver'=>'sqlite', 'database'=>$source, 'prefix'=>'', 'foreign_key_constraints'=>true],
        'integrations.sheets.enabled'=>false, 'session.driver'=>'array', 'cache.default'=>'array', 'mail.default'=>'array']);
    DB::purge();
    requireCheck($kernel->call('migrate', ['--force'=>true]) === 0, 'real_schema_migrated_in_isolation');
    $fixtures = [];
    foreach (['AA', 'BB'] as $index=>$code) {
        $unit = DB::table('erp_units')->insertGetId(['code'=>'DRILL-'.$code, 'name'=>'Synthetic '.$code, 'timezone'=>'UTC']);
        $student = DB::table('erp_students')->insertGetId(['unit_id'=>$unit, 'code'=>'DRILL-S-'.$code, 'name'=>'Synthetic Student '.$code,
            'registered_on'=>'2026-01-01', 'timezone'=>'UTC', 'grade'=>'8', 'syllabus'=>'Synthetic']);
        $amount = 12345 + $index;
        (new Finance())->payment($unit, 1, ['student_id'=>$student, 'amount_paise'=>$amount, 'reference'=>'DRILL-RECEIPT-'.$code]);
        $fixtures[] = [$unit, $student, $amount, $code];
    }
    requireCheck(DB::table('erp_ledger')->count() === 2 && DB::table('erp_audit')->count() === 2, 'synthetic_payments_and_audit_created');
    $digest = databaseDigest(DB::connection()->getPdo());
    DB::disconnect();
    // Safe here only because our isolated fixture has no concurrent writers and its connection is closed.
    requireCheck(copy($source, $backup), 'closed_synthetic_database_copied');
    $hash = hash_file('sha256', $backup);
    $sourceHash = hash_file('sha256', $source);
    file_put_contents($directory.'/tampered.sqlite', file_get_contents($backup).'corruption');
    $rejected = false;
    try { restoreVerified($directory.'/tampered.sqlite', $hash, $directory.'/rejected.sqlite'); }
    catch (RuntimeException $e) { $rejected = true; }
    requireCheck($rejected && !file_exists($directory.'/rejected.sqlite'), 'corrupt_backup_rejected_before_restore');
    restoreVerified($backup, $hash, $restored);
    requireCheck(hash_equals($hash, hash_file('sha256', $restored)), 'restored_file_hash_matches');
    $rejected = false;
    try { restoreVerified($backup, $hash, $source); } catch (RuntimeException $e) { $rejected = true; }
    requireCheck($rejected && hash_equals($sourceHash, hash_file('sha256', $source)), 'existing_destination_cannot_be_overwritten');
    config(['database.connections.sqlite.database'=>$restored]);
    DB::purge();
    $pdo = DB::connection()->getPdo();
    requireCheck($pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'sqlite_integrity_check');
    requireCheck($pdo->query('PRAGMA foreign_key_check')->fetchAll() === [], 'declared_foreign_keys_valid');
    requireCheck(hash_equals($digest, databaseDigest($pdo)), 'all_table_schema_and_rows_preserved');
    requireCheck(DB::table('erp_ledger as l')->leftJoin('erp_students as s', 's.id', '=', 'l.student_id')->where(function ($q) {
        $q->whereNull('s.id')->orWhereColumn('l.unit_id', '<>', 's.unit_id');
    })->count() === 0, 'synthetic_ledger_student_unit_links_valid');
    foreach ($fixtures as [$unit, $student, $amount, $code]) {
        requireCheck((new Finance())->balance($unit, $student) === $amount, 'exact_paise_balance_'.$code);
        (new Finance())->payment($unit, 1, ['student_id'=>$student, 'amount_paise'=>$amount, 'reference'=>'DRILL-RECEIPT-'.$code]);
    }
    requireCheck(hash_equals($digest, databaseDigest($pdo)), 'receipt_replay_does_not_duplicate_ledger_or_audit');
    DB::disconnect(); $pdo = null;
    requireCheck(hash_equals($sourceHash, hash_file('sha256', $source)), 'original_synthetic_source_unchanged');
    echo json_encode(['status'=>'ok', 'scope'=>'synthetic_sqlite_only', 'checks'=>$checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['status'=>'error', 'scope'=>'synthetic_sqlite_only', 'completed_checks'=>$checks, 'error'=>$e->getMessage()], JSON_THROW_ON_ERROR)."\n");
    $failed = true;
} finally {
    if (isset($app)) { try { DB::disconnect(); } catch (Throwable $ignored) {} }
    foreach (glob($directory.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
    rmdir($directory);
}
exit(isset($failed) ? 1 : 0);
