<?php
namespace Tests\Unit;
use App\Infrastructure\Cloudflare\{Gateway,D1Pdo,D1Connection};
use PDO;
use RuntimeException;
use PHPUnit\Framework\TestCase;

class MemoryD1Gateway extends Gateway
{
    public PDO $db;
    public bool $loseResponse = false;
    public bool $failWrites = false;
    public int $commits = 0;
    private array $receipts = [];
    public function __construct()
    {
        $this->db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('CREATE TABLE erp_d1_revision(revision INTEGER); INSERT INTO erp_d1_revision VALUES(0); CREATE TABLE accounts(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, amount INTEGER);');
        foreach (['INSERT','UPDATE','DELETE'] as $operation) $this->db->exec("CREATE TRIGGER erp_d1_{$operation} AFTER {$operation} ON accounts BEGIN UPDATE erp_d1_revision SET revision=revision+1; END");
    }
    public function call(string $operation, array $payload=[]): array
    {
        $revision = (int) $this->db->query('SELECT revision FROM erp_d1_revision')->fetchColumn();
        if ($operation === 'rows_batch') {
            $pages = [];
            foreach ($payload['tables'] as $table) $pages[$table] = $this->call('rows', ['table' => $table, 'cursor' => null]);
            return ['pages' => $pages];
        }
        if ($operation === 'state') return ['revision'=>$revision];
        if ($operation === 'schema') return ['revision'=>$revision,'schema'=>$this->db->query("SELECT type,name,sql FROM sqlite_master WHERE name='accounts'")->fetchAll(PDO::FETCH_ASSOC),'sequences'=>$this->db->query('SELECT * FROM sqlite_sequence')->fetchAll(PDO::FETCH_ASSOC)];
        if ($operation === 'rows') return ['rows'=>$this->db->query('SELECT rowid AS _erp_d1_rowid,* FROM accounts')->fetchAll(PDO::FETCH_ASSOC),'more'=>false];
        if (isset($this->receipts[$payload['id']])) return $this->receipts[$payload['id']];
        if ($this->failWrites || $revision !== $payload['revision']) throw new RuntimeException('Rejected');
        $this->db->beginTransaction();
        try {
            foreach ($payload['statements'] as $statement) $this->db->prepare($statement['sql'])->execute($statement['params']);
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
        $this->commits++;
        $receipt = $this->receipts[$payload['id']] = ['revision'=>(int)$this->db->query('SELECT revision FROM erp_d1_revision')->fetchColumn()];
        if ($this->loseResponse) { $this->loseResponse=false; throw new RuntimeException('Lost response after commit'); }
        return $receipt;
    }
}
class D1TransactionTest extends TestCase
{
    private array $paths=[];
    private function connection(MemoryD1Gateway $remote): D1Connection
    {
        $path=sys_get_temp_dir().'/claps-d1-test-'.bin2hex(random_bytes(10)); $this->paths[]=$path;
        return new D1Connection(new D1Pdo($remote,$path),$path,'',['driver'=>'sqlite','name'=>'d1']);
    }
    protected function tearDown(): void
    {
        gc_collect_cycles();
        foreach($this->paths as $path) foreach (glob($path.'*') as $file) if(is_file($file)) unlink($file);
    }
    public function test_recovery_retries_if_database_changes_during_batched_snapshot(): void
    {
        $remote = new class extends MemoryD1Gateway {
            private int $states = 0;
            public function call(string $operation, array $payload = []): array
            {
                if ($operation === 'state' && ++$this->states === 2) {
                    $this->db->exec("INSERT INTO accounts(name,amount) VALUES('concurrent',75)");
                }
                return parent::call($operation, $payload);
            }
        };
        $db = $this->connection($remote);
        $this->assertSame(75, (int)$db->table('accounts')->where('name','concurrent')->value('amount'));
    }

    public function test_auto_commit_preserves_insert_ids_and_bound_quotes(): void
    {
        $remote=new MemoryD1Gateway; $db=$this->connection($remote);
        $id=$db->table('accounts')->insertGetId(['name'=>"O'Brien ?",'amount'=>10]);
        $this->assertSame(1,$id);
        $this->assertSame("O'Brien ?",$remote->db->query('SELECT name FROM accounts')->fetchColumn());
    }
    public function test_outer_exception_rolls_back_all_business_writes(): void
    {
        $remote=new MemoryD1Gateway; $db=$this->connection($remote);
        try {$db->transaction(function()use($db){$db->table('accounts')->insert(['name'=>'a','amount'=>10]); throw new RuntimeException('Stop');});}catch(RuntimeException){}
        $this->assertSame(0,(int)$remote->db->query('SELECT COUNT(*) FROM accounts')->fetchColumn());
        $this->assertSame(0,$db->table('accounts')->count());
    }
    public function test_nested_savepoint_rolls_back_only_nested_mutations(): void
    {
        $remote=new MemoryD1Gateway; $db=$this->connection($remote);
        $db->transaction(function()use($db){
            $db->table('accounts')->insert(['name'=>'a','amount'=>10]);
            try{$db->transaction(function()use($db){$db->table('accounts')->insert(['name'=>'b','amount'=>20]);throw new RuntimeException('Stop');});}catch(RuntimeException){}
            $db->table('accounts')->where('name','a')->update(['amount'=>30]);
        });
        $this->assertSame(['a'],$remote->db->query('SELECT name FROM accounts')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame(30,(int)$remote->db->query('SELECT amount FROM accounts')->fetchColumn());
        $this->assertSame(1,$remote->commits);
    }
    public function test_lost_response_retries_same_commit_without_duplicate_write(): void
    {
        $remote=new MemoryD1Gateway; $db=$this->connection($remote); $remote->loseResponse=true;
        $db->table('accounts')->insert(['name'=>'a','amount'=>10]);
        $this->assertSame(1,$remote->commits);
        $this->assertSame(1,$db->table('accounts')->count());
    }
    public function test_stale_working_copy_cannot_overwrite_a_concurrent_commit(): void
    {
        $remote=new MemoryD1Gateway; $first=$this->connection($remote);$second=$this->connection($remote);
        $first->table('accounts')->insert(['name'=>'a','amount'=>10]);
        try {$second->table('accounts')->insert(['name'=>'b','amount'=>20]);$this->fail('Must reject stale revision');}catch(RuntimeException $e){$this->assertStringContainsString('not confirmed',$e->getMessage());}
        $this->assertSame(['a'],$remote->db->query('SELECT name FROM accounts')->fetchAll(PDO::FETCH_COLUMN));
        $recovered=$this->connection($remote);
        $this->assertSame(10,(int)$recovered->table('accounts')->where('name','a')->value('amount'));
    }
    public function test_unconfirmed_commit_does_not_leave_local_data_and_disables_reuse(): void
    {
        $remote=new MemoryD1Gateway;$db=$this->connection($remote);$remote->failWrites=true;
        try{$db->table('accounts')->insert(['name'=>'a','amount'=>10]);}catch(RuntimeException){}
        $this->assertSame(0,(int)$remote->db->query('SELECT COUNT(*) FROM accounts')->fetchColumn());
        $this->assertSame(0,(int)$db->getPdo()->query('SELECT COUNT(*) FROM accounts')->fetchColumn());
        $this->expectException(RuntimeException::class);$db->table('accounts')->count();
    }
}
