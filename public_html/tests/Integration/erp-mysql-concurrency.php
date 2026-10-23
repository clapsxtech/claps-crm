<?php
/** Synthetic two-process REPEATABLE READ regression. Never loads application .env. */
declare(strict_types=1);

function fail(string $message): void { throw new RuntimeException($message); }
function envRequired(string $key): string { $value = getenv($key); if ($value === false || $value === '') fail("Explicit {$key} is required."); return $value; }
function awaitFile(string $path, float $seconds = 20): void {
    $until = microtime(true) + $seconds;
    while (!is_file($path)) { if (microtime(true) > $until) fail('Barrier timeout: '.basename($path)); usleep(10000); }
}
function mark(string $path): void { file_put_contents($path, 'ready', LOCK_EX); }
function check(bool $condition, string $message): void { if (!$condition) fail($message); }

$base = dirname(__DIR__, 2);
$artifactRoot = getenv('ERP_PROBE_ARTIFACT_DIR') ?: sys_get_temp_dir().'/erp_mysql_probe';
if (!is_dir($artifactRoot) && !mkdir($artifactRoot, 0700, true)) fail('Cannot create probe artifact directory.');
$resolvedArtifacts = realpath($artifactRoot);
$resolvedBase = realpath($base);
if (!$resolvedArtifacts || !$resolvedBase || $resolvedArtifacts === $resolvedBase || strpos($resolvedArtifacts, $resolvedBase.DIRECTORY_SEPARATOR) === 0) fail('Runtime artifacts must be outside the application repository.');
$artifactRoot = $resolvedArtifacts;
$host = envRequired('ERP_PROBE_HOST');
$port = envRequired('ERP_PROBE_PORT');
$database = envRequired('ERP_PROBE_DATABASE');
$user = envRequired('ERP_PROBE_USER');
$password = getenv('ERP_PROBE_PASSWORD');
if ($host !== '127.0.0.1' || !ctype_digit($port) || (int)$port < 1024 || (int)$port > 65535 || !preg_match('/^erp_probe_[a-z0-9_]+$/D', $database) || $password === false) fail('Unsafe or incomplete probe connection. Only explicit loopback TCP and erp_probe_* databases are accepted.');
if (envRequired('ERP_PROBE_ALLOW_RESET') !== $database) fail('ERP_PROBE_ALLOW_RESET must exactly name the disposable test database.');
if (!extension_loaded('pdo_mysql') || !function_exists('proc_open')) fail('pdo_mysql and proc_open are required.');
require $base.'/vendor/autoload.php';
$app = new Illuminate\Foundation\Application($base);
$app->instance('config', new Illuminate\Config\Repository([
    'app' => ['locale' => 'en'],
    'erp' => ['finance_policy' => 'admin_import_delivered_only', 'payroll_policy' => 'approved_attended_only'],
    'database' => ['default' => 'mysql', 'connections' => ['mysql' => ['driver' => 'mysql', 'host' => $host, 'port' => (int)$port, 'database' => $database, 'username' => $user, 'password' => $password, 'unix_socket' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => 'InnoDB']]],
]));
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
foreach ([Illuminate\Events\EventServiceProvider::class, Illuminate\Database\DatabaseServiceProvider::class, Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Translation\TranslationServiceProvider::class, Illuminate\Validation\ValidationServiceProvider::class] as $provider) $app->register($provider);
$app->boot();
use Illuminate\Support\Facades\DB;
use App\Modules\Erp\Services\Attendance;
use App\Modules\Erp\Services\Scheduling;
use App\Modules\Erp\Services\MoneyOperations;
use App\Modules\Erp\Services\Finance;
DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
check(DB::selectOne('SELECT DATABASE() AS name')->name === $database, 'Connected database does not match explicit test database.');

function migrationProvenance(string $cutoff): void {
    $source = 'SYNTHETIC-CUTOFF-'.$cutoff;
    $batch = DB::table('erp_migration_batches')->insertGetId(['source_id'=>$source,'cutoff'=>$cutoff,'digest'=>hash('sha256',$source),'manifest'=>'{}','counts'=>'{}','totals'=>'{}','actor_id'=>1]);
    foreach (['student'=>'erp_students','teacher'=>'erp_teachers','assignment'=>'erp_enrollments'] as $type=>$table) {
        DB::table('erp_migration_mappings')->insert(['source_id'=>$source,'entity_type'=>$type,'external_id'=>'PROBE-'.$type,'target_type'=>$table,'target_id'=>1,'row_digest'=>hash('sha256',$type),'batch_id'=>$batch]);
    }
}
function executeOperation(string $operation, string $dir, string $name): array {
    if ($operation === 'cutoff_hold') return DB::transaction(function() use($dir,$name) {
        DB::table('erp_teachers')->where('id',1)->lockForUpdate()->first();
        DB::table('erp_students')->where('id',1)->lockForUpdate()->first();
        mark($dir.'/'.$name.'.locked'); awaitFile($dir.'/release');
        migrationProvenance('2026-01-06');
        return ['committed_cutoff'=>'2026-01-06'];
    });

    if ($operation === 'settle') return (array)(new MoneyOperations())->recordTeacherPayment(1,1,['teacher_id'=>1,'amount_paise'=>4000,'reference'=>'SETTLE-'.$name,'paid_on'=>gmdate('Y-m-d'),'reason'=>'Synthetic already-paid settlement','already_paid'=>true]);
    if ($operation === 'allocate_receipt_cap') return (array)(new MoneyOperations())->allocateReceipt(1,1,['request_id'=>$name==='b'?2:1,'payment_id'=>1,'amount_paise'=>6000]);
    if ($operation === 'allocate_request_cap') return (array)(new MoneyOperations())->allocateReceipt(1,1,['request_id'=>1,'payment_id'=>$name==='b'?2:1,'amount_paise'=>6000]);
    if ($operation === 'correct_receipt') return (array)(new MoneyOperations())->correctReceipt(1,1,['entry_id'=>1,'amount_paise'=>15000,'new_reference'=>'CORRECTED-RECEIPT','reason'=>'Synthetic correction']);
    if ($operation === 'reverse_receipt_hold') return DB::transaction(function() use($dir,$name) {
        DB::table('erp_students')->where('id',1)->lockForUpdate()->first();
        mark($dir.'/'.$name.'.locked'); awaitFile($dir.'/release');
        return (array)(new MoneyOperations())->reverseReceipt(1,1,['entry_id'=>1,'reason'=>'Synthetic erroneous receipt']);
    });

    if ($operation === 'approve') return (array)(new Attendance())->approve(1, 1, 1);
    // Match teacher-controller nesting: the outermost transaction must own deadlock retries.
    if ($operation === 'submit_distinct') return DB::transaction(function() use($name) {
        $id=$name==='b'?2:1;
        DB::table('erp_teachers')->where('id',$id)->lockForUpdate()->first();
        return (array)(new Attendance())->submit(1,1,['lesson_id'=>$id,'actual_minutes'=>60]);
    },3);
    if ($operation === 'submit') return (array)(new Attendance())->submit(1, 1, ['lesson_id' => 1, 'actual_minutes' => 60]);
    if ($operation === 'import') return (array)(new Attendance())->importBatch(1, 1, ['attendance_ids' => [1], 'reference' => 'PROBE-'.$name]);
    if ($operation === 'cancel_hold' || $operation === 'correct_hold') {
        return DB::transaction(function () use ($operation, $dir, $name) {
            DB::table('erp_teachers')->where('id', 1)->lockForUpdate()->first();
            mark($dir.'/'.$name.'.locked');
            awaitFile($dir.'/release');
            if ($operation === 'cancel_hold') return (array)(new Scheduling())->cancel(1, 1, ['lesson_id' => 1, 'reason' => 'Synthetic concurrent cancellation']);
            return (array)(new Attendance())->correct(1, 1, 1, ['actual_minutes' => 30, 'reason' => 'Synthetic concurrent correction']);
        });
    }
    fail('Unknown synthetic operation.');
}

if (($argv[1] ?? '') === 'worker') {
    [$script, $mode, $operation, $dir, $name] = $argv;
    try {
        // Signal after an ordinary discovery read, before the teacher lock can return.
        if ($operation === 'submit_distinct') {
            $shared = false;
            DB::listen(function($query) use(&$shared,$dir,$name) {
                if (!$shared && strpos($query->sql,'erp_migration_batches')!==false) {
                    $shared=true; mark($dir.'/'.$name.'.shared');
                    awaitFile($dir.'/'.($name==='a'?'b':'a').'.shared');
                }
            });
        }
        $traced = false;
        DB::listen(function ($query) use (&$traced, $dir, $name, $operation) {
            $needle = strpos($operation, 'allocate_') === 0 ? 'erp_payment_requests' : ($operation === 'submit' ? 'erp_lessons' : 'erp_attendance');
            if (!$traced && stripos($query->sql, 'select') === 0 && strpos($query->sql, $needle) !== false && stripos($query->sql, 'for update') === false) {
                $traced = true; mark($dir.'/'.$name.'.discovered');
            }
        });
        mark($dir.'/'.$name.'.ready'); awaitFile($dir.'/start');
        $result = executeOperation($operation, $dir, $name);
        file_put_contents($dir.'/'.$name.'.json', json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR));
    } catch (Throwable $e) {
        file_put_contents($dir.'/'.$name.'.json', json_encode(['ok' => false, 'class' => get_class($e), 'message' => $e->getMessage()], JSON_THROW_ON_ERROR));
    }
    exit;
}

require_once $base.'/database/migrations/2026_10_23_130000_create_erp_core_tables.php';
require_once $base.'/database/migrations/2026_10_23_150000_create_erp_business_workflows.php';
require_once $base.'/database/migrations/2026_10_23_160000_index_erp_migration_targets.php';
function resetFixture(): void {
    $migration = new CreateErpCoreTables();
    $business = new CreateErpBusinessWorkflows();
    $business->down(); $migration->down(); $migration->up(); $business->up(); (new IndexErpMigrationTargets())->up();
    DB::table('erp_units')->insert(['id' => 1, 'code' => 'PROBE', 'name' => 'Synthetic concurrency unit', 'timezone' => 'UTC']);
    DB::table('erp_students')->insert(['id' => 1, 'unit_id' => 1, 'code' => 'PROBE-S', 'name' => 'Synthetic student', 'registered_on' => '2026-01-01', 'timezone' => 'UTC', 'status' => 'active', 'grade' => '8', 'syllabus' => 'Synthetic']);
    DB::table('erp_teachers')->insert(['id' => 1, 'code' => 'PROBE-T', 'name' => 'Synthetic teacher', 'timezone' => 'UTC', 'status' => 'active']);
    DB::table('erp_teacher_units')->insert(['teacher_id' => 1, 'unit_id' => 1]);
    DB::table('erp_subjects')->insert(['id' => 1, 'name' => 'Synthetic subject']);
    DB::table('erp_enrollments')->insert(['id' => 1, 'unit_id' => 1, 'student_id' => 1, 'teacher_id' => 1, 'subject_id' => 1, 'starts_on' => '2026-01-01', 'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR', 'status' => 'active']);
    DB::table('erp_lessons')->insert(['id' => 1, 'unit_id' => 1, 'enrollment_id' => 1, 'student_id' => 1, 'teacher_id' => 1, 'subject_id' => 1, 'starts_at' => '2026-01-05 10:00:00', 'ends_at' => '2026-01-05 11:00:00', 'status' => 'scheduled', 'kind' => 'regular', 'student_rate_paise' => 12000, 'teacher_rate_paise' => 6000, 'currency' => 'INR']);
}
function worker(string $operation, string $dir, string $name) {
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $operation, $dir, $name], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $dir.'/'.$name.'.stdout', 'w'], 2 => ['file', $dir.'/'.$name.'.stderr', 'w']], $pipes);
    if (!is_resource($process)) fail('Failed to start worker.');
    return $process;
}
function results(array $processes, string $dir): array {
    $results=[];
    foreach ($processes as $name=>$process) {
        try { awaitFile($dir.'/'.$name.'.json'); $results[$name]=json_decode(file_get_contents($dir.'/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR); }
        finally { $status=proc_get_status($process); if ($status['running']) proc_terminate($process); proc_close($process); }
    }
    return $results;
}
function directory(string $name): string { global $artifactRoot; $dir=$artifactRoot.'/runs/'.gmdate('Ymd_His').'_'.getmypid().'_'.$name; if (!mkdir($dir, 0700, true)) fail('Cannot create barrier directory.'); return $dir; }
function simultaneous(string $operation, string $name): array {
    $dir=directory($name); $processes=['a'=>worker($operation,$dir,'a'),'b'=>worker($operation,$dir,'b')];
    awaitFile($dir.'/a.ready'); awaitFile($dir.'/b.ready'); mark($dir.'/start');
    return results($processes,$dir);
}
function interleaved(string $holder, string $waiter, string $name): array {
    $dir=directory($name); $processes=['holder'=>worker($holder,$dir,'holder')];
    awaitFile($dir.'/holder.ready'); mark($dir.'/start'); awaitFile($dir.'/holder.locked');
    $processes['waiter']=worker($waiter,$dir,'waiter'); awaitFile($dir.'/waiter.discovered');
    mark($dir.'/release'); return results($processes,$dir);
}
function allOk(array $results): void { foreach ($results as $name=>$result) check($result['ok'], $name.' failed: '.json_encode($result)); }
function ledgerBalance(): int { return (int)DB::table('erp_ledger')->where('student_id',1)->sum('amount_paise'); }

function exactlyOneAccepted(array $results): void {
    check(count(array_filter($results, function($r){return $r['ok'];}))===1, 'Expected exactly one accepted concurrent operation: '.json_encode($results));
    foreach ($results as $result) if (!$result['ok']) check($result['class']===Illuminate\Validation\ValidationException::class, 'Expected domain rejection, not database failure: '.json_encode($result));
}
function requestFixture(string $reference): void {
    (new MoneyOperations())->createPaymentRequest(1,1,['student_id'=>1,'amount_paise'=>10000,'reference'=>$reference,'requested_on'=>gmdate('Y-m-d')]);
}
function receiptFixture(string $reference): void {
    (new Finance())->payment(1,1,['student_id'=>1,'amount_paise'=>10000,'reference'=>$reference]);
}

$report=['database'=>$database,'server'=>DB::selectOne('SELECT VERSION() AS version')->version,'isolation'=>'REPEATABLE READ','scenarios'=>[]];
try {
    resetFixture(); $r=simultaneous('submit','same_lesson'); allOk($r);
    check(DB::table('erp_attendance')->count()===1 && $r['a']['result']['id']===$r['b']['result']['id'], 'Duplicate attendance under concurrent replay.');
    check(DB::table('erp_audit')->where('action','attendance.submitted')->count()===1, 'Duplicate submission audit after concurrent replay.');
    $report['scenarios']['simultaneous_attendance']='PASS';

    $r=simultaneous('import','distinct_import_refs'); allOk($r);
    check(DB::table('erp_attendance_batches')->count()===2 && DB::table('erp_ledger')->where('kind','lesson_charge')->count()===1 && ledgerBalance()===-12000, 'Concurrent distinct imports duplicated or lost the charge.');
    $report['scenarios']['distinct_import_refs_exactly_one_charge']='PASS';

    $r=simultaneous('approve','concurrent_approval'); allOk($r);
    check(DB::table('erp_ledger')->where('kind','teacher_accrual')->count()===1 && (int)DB::table('erp_ledger')->where('teacher_id',1)->sum('amount_paise')===6000 && ledgerBalance()===-12000, 'Concurrent approval duplicated teacher accrual or changed student charge.');
    $report['scenarios']['simultaneous_approval_exactly_one_teacher_accrual']='PASS';

    resetFixture(); $r=interleaved('cancel_hold','submit','cancel_before_attendance');
    check($r['holder']['ok'] && !$r['waiter']['ok'] && $r['waiter']['class']===Illuminate\Validation\ValidationException::class, 'Attendance did not reject committed concurrent cancellation.');
    check(DB::table('erp_lessons')->where('id',1)->value('status')==='cancelled' && DB::table('erp_attendance')->count()===0 && DB::table('erp_ledger')->count()===0, 'Cancelled lesson gained attendance or charge.');
    $report['scenarios']['cancellation_wins_attendance_race']='PASS';

    resetFixture(); (new Attendance())->submit(1,1,['lesson_id'=>1,'actual_minutes'=>60]);
    (new Attendance())->importBatch(1,1,['attendance_ids'=>[1],'reference'=>'PROBE-INITIAL']);
    (new Attendance())->approve(1,1,1);
    $r=interleaved('correct_hold','import','correction_before_reimport'); allOk($r);
    check(ledgerBalance()===-6000 && DB::table('erp_ledger')->count()===5 && DB::table('erp_ledger')->where('kind','reversal')->count()===2, 'Correction/reimport ledger invariants failed.');
    check((int)DB::table('erp_ledger')->where('teacher_id',1)->sum('amount_paise')===0, 'Corrected teacher pay accrued before fresh approval.');
    (new Attendance())->approve(1,1,1);
    check((int)DB::table('erp_ledger')->where('teacher_id',1)->sum('amount_paise')===3000 && DB::table('erp_ledger')->count()===6, 'Fresh approval did not independently accrue corrected teacher pay.');
    $report['scenarios']['correction_reimport_compensating_ledger']='PASS';
    resetFixture(); (new Attendance())->submit(1,1,['lesson_id'=>1,'actual_minutes'=>60]); (new Attendance())->approve(1,1,1);
    $r=simultaneous('settle','competing_settlements'); exactlyOneAccepted($r);
    check(DB::table('erp_ledger')->where('kind','teacher_payment')->count()===1 && (new MoneyOperations())->teacherStatement(1,1)['net_payable_paise']===2000, 'Competing settlements overspent payable.');
    $report['scenarios']['competing_settlements_capped_to_payable']='PASS';

    resetFixture(); receiptFixture('CAP-RECEIPT'); requestFixture('CAP-REQUEST-A'); requestFixture('CAP-REQUEST-B');
    $r=simultaneous('allocate_receipt_cap','receipt_allocation_cap'); exactlyOneAccepted($r);
    check((int)DB::table('erp_request_allocations')->sum('amount_paise')===6000, 'Competing requests overallocated one receipt.');
    $report['scenarios']['competing_allocations_receipt_cap']='PASS';

    resetFixture(); receiptFixture('CAP-RECEIPT-A'); receiptFixture('CAP-RECEIPT-B'); requestFixture('CAP-REQUEST');
    $r=simultaneous('allocate_request_cap','request_allocation_cap'); exactlyOneAccepted($r);
    check((new MoneyOperations())->requestSummary(1,1)['covered_paise']===6000, 'Competing receipts overallocated one request.');
    $report['scenarios']['competing_allocations_request_cap']='PASS';

    resetFixture(); receiptFixture('RACE-RECEIPT'); requestFixture('RACE-REQUEST');
    $r=interleaved('reverse_receipt_hold','allocate_receipt_cap','reversal_before_allocation');
    check($r['holder']['ok'] && !$r['waiter']['ok'] && $r['waiter']['class']===Illuminate\Validation\ValidationException::class, 'Allocation did not observe committed receipt reversal.');
    check((new MoneyOperations())->requestSummary(1,1)['covered_paise']===0 && DB::table('erp_request_allocations')->count()===0, 'Reversed receipt incorrectly covers a request.');
    $report['scenarios']['receipt_reversal_allocation_current_read']='PASS';

    resetFixture(); receiptFixture('ORIGINAL-CORRECTION');
    DB::unprepared("CREATE TRIGGER probe_fail_replacement BEFORE INSERT ON erp_ledger FOR EACH ROW BEGIN IF NEW.reference = 'FAIL-REPLACEMENT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic replacement failure'; END IF; END");
    $rolledBack=false;
    try { (new MoneyOperations())->correctReceipt(1,1,['entry_id'=>1,'amount_paise'=>15000,'new_reference'=>'FAIL-REPLACEMENT','reason'=>'Synthetic rollback']); }
    catch (Illuminate\Database\QueryException $expected) { $rolledBack=true; }
    finally { DB::unprepared('DROP TRIGGER probe_fail_replacement'); }
    check($rolledBack && ledgerBalance()===10000 && DB::table('erp_ledger')->whereNotNull('reverses_id')->count()===0, 'Correction failure left partial reversal.');
    $r=simultaneous('correct_receipt','concurrent_receipt_correction'); allOk($r);
    check($r['a']['result']['id']===$r['b']['result']['id'] && ledgerBalance()===15000 && DB::table('erp_ledger')->count()===3, 'Concurrent correction was not atomic/idempotent.');
    $report['scenarios']['receipt_correction_rollback_and_concurrent_replay']='PASS';
    resetFixture(); migrationProvenance('2026-01-05');
    allOk(simultaneous('submit','mapped_simultaneous_submit'));
    allOk(simultaneous('import','mapped_simultaneous_import'));
    allOk(simultaneous('approve','mapped_simultaneous_approve'));
    check(DB::table('erp_attendance')->count()===1 && DB::table('erp_ledger')->where('kind','lesson_charge')->count()===1 && DB::table('erp_ledger')->where('kind','teacher_accrual')->count()===1, 'Mapped concurrent operations duplicated financial events.');
    $report['scenarios']['mapped_provenance_concurrent_attendance_and_both_streams']='PASS';

    resetFixture(); $r=interleaved('cutoff_hold','submit','cutoff_committed_before_submit');
    check($r['holder']['ok'] && !$r['waiter']['ok'] && $r['waiter']['class']===Illuminate\Validation\ValidationException::class, 'Waiter missed newly committed migration cutoff.');
    check(DB::table('erp_attendance')->count()===0 && DB::table('erp_ledger')->count()===0, 'Pre-cutoff race wrote attendance or ledger.');
    $report['scenarios']['new_committed_cutoff_visible_after_parent_lock']='PASS';
    resetFixture(); migrationProvenance('2026-01-05');
    foreach (['erp_students','erp_teachers','erp_enrollments','erp_lessons'] as $table) {
        $row=(array)DB::table($table)->where('id',1)->first(); $row['id']=2;
        if (isset($row['code'])) $row['code'].='-SECOND';
        foreach (['student_id','teacher_id','enrollment_id'] as $field) if (isset($row[$field])) $row[$field]=2;
        DB::table($table)->insert($row);
    }
    DB::table('erp_teacher_units')->insert(['teacher_id'=>2,'unit_id'=>1]);
    foreach (DB::table('erp_migration_mappings')->get() as $mapping) {
        $row=(array)$mapping; unset($row['id']); $row['target_id']=2; $row['external_id'].='-SECOND'; DB::table('erp_migration_mappings')->insert($row);
    }
    foreach ([1,2] as $owner) {
        $opening=DB::table('erp_ledger')->insertGetId(['unit_id'=>1,'student_id'=>$owner,'kind'=>'opening_balance','amount_paise'=>0,'currency'=>'INR','reference'=>'SYN-OPENING-'.$owner,'event_key'=>'syn-opening-'.$owner,'occurred_at'=>'2026-01-05 00:00:00','created_by'=>1]);
        DB::table('erp_migration_mappings')->insert(['source_id'=>'SYNTHETIC-CUTOFF-2026-01-05','entity_type'=>'opening_balance','external_id'=>'OPENING-'.$owner,'target_type'=>'erp_ledger','target_id'=>$opening,'row_digest'=>hash('sha256','opening-'.$owner),'batch_id'=>1]);
    }
    allOk(simultaneous('submit_distinct','unrelated_parties_shared_batch'));
    check(DB::table('erp_attendance')->count()===2, 'Unrelated mapped parties did not both submit.');
    check(DB::table('erp_audit')->where('action','attendance.submitted')->count()===2 && DB::table('erp_ledger')->count()===2, 'Retried submission duplicated audit or posted financial events.');
    $report['scenarios']['unrelated_parties_share_immutable_batch_current_read']='PASS';
    $report['status']='PASS';
} catch (Throwable $e) { $report['status']='FAIL'; $report['error']=$e->getMessage(); }
file_put_contents($artifactRoot.'/last-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['status']==='PASS'?0:1);
