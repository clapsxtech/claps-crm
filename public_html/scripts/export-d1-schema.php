<?php
// Generate the initial D1 schema from Laravel's migrations using an isolated empty database.
require dirname(__DIR__).'/vendor/autoload.php';
$directory=sys_get_temp_dir().'/claps-d1-schema-'.bin2hex(random_bytes(8));mkdir($directory,0700);
$path=$directory.'/schema.sqlite';touch($path);
foreach(['APP_ENV'=>'testing','APP_CONFIG_CACHE'=>$directory.'/config.php','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$path,'CACHE_DRIVER'=>'array','SESSION_DRIVER'=>'array','GOOGLE_SERVICE_ENABLED'=>'false'] as $k=>$v){putenv($k.'='.$v);$_ENV[$k]=$_SERVER[$k]=$v;}
$app=require dirname(__DIR__).'/bootstrap/app.php';$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);$kernel->bootstrap();
if($kernel->call('migrate',['--force'=>true])!==0)throw new RuntimeException($kernel->output());
$pdo=Illuminate\Support\Facades\DB::connection()->getPdo();
$objects=$pdo->query("SELECT type,name,sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END,name")->fetchAll(PDO::FETCH_ASSOC);
$sql="-- Generated from Laravel migrations; new empty D1 database only.\nPRAGMA defer_foreign_keys=ON;\n";
foreach($objects as $object)$sql.=$object['sql'].";\n";
$sql.="CREATE TABLE erp_d1_revision(id INTEGER PRIMARY KEY CHECK(id=1), revision INTEGER NOT NULL);\nINSERT INTO erp_d1_revision VALUES(1,0);\nCREATE TABLE erp_d1_guard(ok INTEGER NOT NULL CHECK(ok=1));\nCREATE TABLE erp_d1_commits(id TEXT PRIMARY KEY, hash TEXT NOT NULL, revision INTEGER NOT NULL);\n";
foreach($objects as $object){if($object['type']!=='table')continue;$table=App\Infrastructure\Cloudflare\D1Pdo::identifier($object['name']);foreach(['INSERT','UPDATE','DELETE'] as $action){$trigger=App\Infrastructure\Cloudflare\D1Pdo::identifier('erp_d1_rev_'.$object['name'].'_'.strtolower($action));$sql.="CREATE TRIGGER {$trigger} AFTER {$action} ON {$table} BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;\n";}}
foreach($pdo->query('SELECT * FROM migrations')->fetchAll(PDO::FETCH_ASSOC) as $row){$values=array_map(fn($v)=>is_int($v)?(string)$v:$pdo->quote($v),array_values($row));$sql.='INSERT INTO migrations(id,migration,batch) VALUES('.implode(',',$values).");\n";}
$target=dirname(__DIR__).'/cloudflare/migrations/0001_initial.sql';file_put_contents($target,$sql);
$pdo=null;Illuminate\Support\Facades\DB::disconnect();unlink($path);rmdir($directory);
fwrite(STDOUT,'Generated initial D1 schema: '.count($objects)." schema objects.\n");
