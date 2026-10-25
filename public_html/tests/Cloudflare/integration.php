<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$directory=sys_get_temp_dir().'/claps-real-d1-'.bin2hex(random_bytes(8));mkdir($directory,0700);
foreach(['APP_ENV'=>'testing','APP_CONFIG_CACHE'=>$directory.'/config.php','DB_CONNECTION'=>'d1','CLOUDFLARE_GATEWAY_URL'=>'http://localhost:8790','CLOUDFLARE_INTERNAL_TOKEN'=>'local-test-only'] as $k=>$v){putenv($k.'='.$v);$_ENV[$k]=$_SERVER[$k]=$v;}
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function($e){fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");exit(1);});
config(['database.connections.d1.database'=>$directory.'/working.sqlite']);
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Infrastructure\Cloudflare\{Gateway,D1Pdo,D1Connection};
use App\Modules\Erp\Services\Finance;
function check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS: {$message}\n";}
$db=DB::connection();$suffix=bin2hex(random_bytes(6));
$unit=$db->table('erp_units')->insertGetId(['code'=>'D1-'.$suffix,'name'=>'D1 integration','timezone'=>'UTC']);
$student=$db->table('erp_students')->insertGetId(['unit_id'=>$unit,'code'=>'S-'.$suffix,'name'=>'D1 student','grade'=>'8','syllabus'=>'Synthetic','registered_on'=>'2026-01-01','timezone'=>'UTC']);
$receipt=(new Finance)->payment($unit,1,['student_id'=>$student,'amount_paise'=>12345,'reference'=>'D1-'.$suffix]);
$again=(new Finance)->payment($unit,1,['student_id'=>$student,'amount_paise'=>12345,'reference'=>'D1-'.$suffix]);
check($receipt->id===$again->id,'Payment replay does not duplicate the ledger');
check((new Finance)->balance($unit,$student)===12345,'Exact paise balance');
try{$db->transaction(function()use($db,$unit){$db->table('erp_units')->where('id',$unit)->update(['name'=>'SHOULD ROLLBACK']);throw new RuntimeException('rollback');});}catch(RuntimeException){}
check($db->table('erp_units')->where('id',$unit)->value('name')==='D1 integration','Rollback restores state');
$gateway=new Gateway('http://localhost:8790','local-test-only');
$recovered=new D1Connection(new D1Pdo($gateway,$directory.'/recovered.sqlite'),'recovered','',['driver'=>'sqlite','name'=>'recovered']);
check((int)$recovered->table('erp_ledger')->where('id',$receipt->id)->value('amount_paise')===12345,'Restart restores committed ledger from D1');
$db->table('erp_units')->where('id',$unit)->update(['name'=>'New revision']);
try{$recovered->table('erp_units')->where('id',$unit)->update(['name'=>'STALE']);throw new LogicException('Stale write accepted');}catch(App\Infrastructure\Cloudflare\UnconfirmedCommit){}
check($db->table('erp_units')->where('id',$unit)->value('name')==='New revision','Stale writer is rejected');
$id=bin2hex(random_bytes(24));$revision=$gateway->call('state')['revision'];
$payload=['id'=>$id,'revision'=>$revision,'statements'=>[['sql'=>'UPDATE erp_units SET name=? WHERE id=?','params'=>["Quote ' and ?",$unit]]]];
$first=$gateway->call('commit',$payload);$retry=$gateway->call('commit',$payload);
check($first===$retry,'Gateway commit receipt makes retry idempotent');
$bad=['id'=>bin2hex(random_bytes(24)),'revision'=>$first['revision'],'statements'=>[['sql'=>'UPDATE erp_units SET name=? WHERE id=?','params'=>['partial',$unit]],['sql'=>'INSERT INTO missing_table(x) VALUES (?)','params'=>[1]]]];
try{$gateway->call('commit',$bad);throw new LogicException('Invalid batch accepted');}catch(RuntimeException){}
$final=new D1Connection(new D1Pdo($gateway,$directory.'/final.sqlite'),'final','',['driver'=>'sqlite','name'=>'final']);
check($final->table('erp_units')->where('id',$unit)->value('name')==="Quote ' and ?",'A failed D1 batch cannot leave partial business writes');
check(Http::post('http://localhost:8790/__cloudflare/database/state',[])->status()===401,'Database gateway denies unauthenticated access');
$key='profile_photo/'.Illuminate\Support\Str::uuid().'.png';$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
check(Http::withToken('local-test-only')->withBody($png,'image/png')->put('http://localhost:8790/__cloudflare/uploads/'.$key)->successful(),'R2 upload');
check(Http::get('http://localhost:8790/uploads/'.$key)->body()===$png,'R2 read preserves uploaded bytes');
check(Http::withToken('local-test-only')->delete('http://localhost:8790/__cloudflare/uploads/'.$key)->successful(),'R2 delete');
echo "D1/R2 integration checks passed. Synthetic local D1 only.\n";
