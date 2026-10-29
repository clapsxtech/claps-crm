<?php
namespace Tests\Support;
use App\Infrastructure\Cloudflare\{Gateway,D1Pdo};
use PDO;
use RuntimeException;

/** Test double for D1's atomic batch API; never connects to Cloudflare. */
class SqliteD1Gateway extends Gateway
{
    private PDO $remote;
    private array $receipts=[];
    public function __construct(PDO $source)
    {
        $this->remote=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $schema=$source->query("SELECT type,name,sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END,name")->fetchAll(PDO::FETCH_ASSOC);
        foreach($schema as $object)$this->remote->exec($object['sql']);
        foreach($schema as $object){if($object['type']!=='table')continue;$table=D1Pdo::identifier($object['name']);foreach($source->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC) as $row){$this->remote->prepare('INSERT INTO '.$table.' ('.implode(',',array_map(D1Pdo::identifier(...),array_keys($row))).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));}}
        $this->remote->exec('CREATE TABLE erp_d1_revision(revision INTEGER);INSERT INTO erp_d1_revision VALUES(0);PRAGMA foreign_keys=ON;');
        foreach($schema as $object){if($object['type']!=='table')continue;$table=D1Pdo::identifier($object['name']);foreach(['INSERT','UPDATE','DELETE'] as $action)$this->remote->exec('CREATE TRIGGER '.D1Pdo::identifier('erp_d1_'.$object['name'].'_'.$action)." AFTER {$action} ON {$table} BEGIN UPDATE erp_d1_revision SET revision=revision+1;END;");}
    }
    public function call(string $operation,array $payload=[]):array
    {
        $revision=(int)$this->remote->query('SELECT revision FROM erp_d1_revision')->fetchColumn();
        if ($operation === 'rows_batch') {
            $pages = [];
            foreach ($payload['tables'] as $table) $pages[$table] = $this->call('rows', ['table' => $table, 'cursor' => $payload['cursors'][$table] ?? -1, 'limit'=>$payload['limit']??200]);
            return ['pages' => $pages];
        }
        if($operation==='state')return ['revision'=>$revision];
        if($operation==='schema')return ['revision'=>$revision,'schema'=>$this->remote->query("SELECT type,name,sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' AND substr(name,1,7)!='erp_d1_' ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END,name")->fetchAll(PDO::FETCH_ASSOC),'sequences'=>$this->remote->query('SELECT * FROM sqlite_sequence')->fetchAll(PDO::FETCH_ASSOC)];
        if($operation==='rows'){
            $limit=(int)($payload['limit']??200);$cursor=(int)($payload['cursor']??-1);
            $rows=$this->remote->query('SELECT rowid AS _erp_d1_rowid,* FROM '.D1Pdo::identifier($payload['table']).' WHERE rowid>'.$cursor.' ORDER BY rowid LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);
            return ['rows'=>$rows,'more'=>count($rows)===$limit];
        }
        if(isset($this->receipts[$payload['id']]))return $this->receipts[$payload['id']];
        if($revision!==$payload['revision'])throw new RuntimeException('Conflict');
        $this->remote->beginTransaction();
        try{foreach($payload['statements'] as $s)$this->remote->prepare($s['sql'])->execute($s['params']);$this->remote->commit();}catch(\Throwable $e){$this->remote->rollBack();throw $e;}
        return $this->receipts[$payload['id']]=['revision'=>(int)$this->remote->query('SELECT revision FROM erp_d1_revision')->fetchColumn()];
    }
}
