<?php
namespace Tests\Feature;
use App\Modules\Erp\Services\SourceCsvInventory;
use Tests\TestCase;
class ErpSourceCsvInventoryTest extends TestCase
{
    private $directory;
    protected function setUp():void{parent::setUp();$this->directory=sys_get_temp_dir().'/erp-source-synthetic-'.bin2hex(random_bytes(6));mkdir($this->directory);}
    protected function tearDown():void{foreach(glob($this->directory.'/*') as $file)unlink($file);rmdir($this->directory);parent::tearDown();}
    private function source(string $file,array $rows,string $kind,array $columns,int $header=0,string $start='A'):array{
        $h=fopen($this->directory.'/'.$file,'w');foreach($rows as $row)fputcsv($h,$row);fclose($h);
        return ['file'=>$file,'book'=>'SYNTHETIC','sheet'=>'Synthetic rows','range'=>$start.'1:H'.count($rows),'rows'=>count($rows),'timezone'=>'Asia/Kolkata','sha256'=>hash_file('sha256',$this->directory.'/'.$file),'columns'=>$columns,'kind'=>$kind,'header_rows'=>$header];
    }
    private function manifest(array $sources):array{return ['schema_version'=>1,'source_id'=>'SYNTHETIC-NOT-REAL','exported_at'=>'2026-10-23T00:00:00Z','cutoff_date'=>null,'student_openings_approved'=>false,'sources'=>$sources];}
    private function attendance(string $file,string $hours='0.5'):array{return $this->source($file,[['ID','Teacher','Teacher Code','Student','Student Code','Date','Hours','Unit'],['SYN-A1','Synthetic Teacher','SYN-T1','Synthetic Student','SYN-S1','2026-10-01',$hours,'AA']],'attendance',['attendance_id'=>'A','teacher_id'=>'C','student_id'=>'E','date'=>'F','hours'=>'G','unit'=>'H'],1);}
    public function test_inventory_deduplicates_exact_mirrors_without_inventing_missing_semantics():void{
        $s=new SourceCsvInventory();$first=$this->attendance('first.csv');$first['timezone']='Asia/Calcutta';$r=$s->inspect($this->manifest([$first,$this->attendance('mirror.csv')]),$this->directory);
        $this->assertSame(['physical_records'=>2,'unique_records'=>1,'mirror_records'=>1,'actual_minutes'=>30],$r['attendance']);$this->assertFalse($r['can_import']);$this->assertSame(0,$r['writes']);
        $codes=array_column($r['issues'],'code');foreach(['missing_or_invalid_approved_cutoff','student_opening_balances_not_approved','teacher_opening_balances_unsupported','attendance_exact_start_missing','attendance_delivery_status_missing'] as $code)$this->assertContains($code,$codes);
    }
    public function test_conflicting_identity_blocks_totals_and_strict_decimal_parsing_rejects_ambiguity():void{
        $s=new SourceCsvInventory();$r=$s->inspect($this->manifest([$this->attendance('first.csv'),$this->attendance('conflict.csv','1')]),$this->directory);$this->assertNull($r['attendance']['actual_minutes']);$this->assertContains('conflicting_attendance_identity',array_column($r['issues'],'code'));
        $this->assertSame(20000,$s->decimalPaise('200.00'));$this->assertSame(-230000,$s->decimalPaise('-2300'));$this->assertSame(690,$s->decimalMinutes('11.5'));
        foreach(['1e2','1,000',' 2','2.001','NaN','01.00','999999999999999999'] as $bad){try{$s->decimalPaise($bad);$this->fail('Ambiguous currency accepted');}catch(\InvalidArgumentException $e){$this->assertTrue(true);}}
        try{$s->decimalMinutes('0.3333');$this->fail('Fractional minute accepted');}catch(\InvalidArgumentException $e){$this->assertTrue(true);}
    }
    public function test_manifest_digests_paths_columns_dates_and_missing_values_fail_closed():void{
        $s=new SourceCsvInventory();$this->assertSame('invalid_manifest_schema',$s->inspect([],$this->directory)['issues'][0]['code']);
        $base=$this->attendance('one.csv');foreach([['sha256'=>str_repeat('0',64)],['file'=>'../outside.csv'],['columns'=>['attendance_id'=>'Z']],['range'=>'A1:H9']] as $change){$r=$s->inspect($this->manifest([array_replace($base,$change)]),$this->directory);$this->assertEmpty($r['inventory']);$this->assertNull($r['attendance']['actual_minutes']);$this->assertFalse($r['inventory_complete']);}
        $missing=$this->source('missing.csv',[['SYN-A1','','SYN-T1','','SYN-S1','','0.5','AA']],'attendance',$base['columns']);$r=$s->inspect($this->manifest([$missing]),$this->directory);$this->assertNull($r['attendance']['actual_minutes']);$this->assertContains('invalid_required_value_or_precision',array_column($r['issues'],'code'));
    }
    public function test_manifest_limits_and_partial_attendance_sources_fail_closed():void{
        $s=new SourceCsvInventory();$source=$this->attendance('good.csv');$bad=$this->attendance('bad.csv');$bad['sha256']=str_repeat('0',64);
        $r=$s->inspect($this->manifest([$source,$bad]),$this->directory);$this->assertSame(1,$r['attendance']['unique_records']);$this->assertNull($r['attendance']['actual_minutes']);$this->assertFalse($r['inventory_complete']);
        foreach([[],array_fill(0,101,$source),['named'=>$source]] as $sources){$r=$s->inspect($this->manifest($sources),$this->directory);$this->assertSame('invalid_manifest_schema',$r['issues'][0]['code']);}
        $source['rows']=10001;$r=$s->inspect($this->manifest([$source]),$this->directory);$this->assertContains('source_row_limit',array_column($r['issues'],'code'));$this->assertNull($r['attendance']['actual_minutes']);
    }
    public function test_observed_balance_arithmetic_never_becomes_an_approved_opening_and_cli_is_read_only():void{
        $source=$this->source('balance.csv',[['-1000','1000','2300','-2300']],'due',['prior'=>'A','received'=>'B','charges'=>'C','current'=>'D']);$manifest=$this->manifest([$source]);$r=(new SourceCsvInventory())->inspect($manifest,$this->directory);$this->assertTrue($r['observed_balances'][0]['arithmetic_matches']);$this->assertNull($r['observed_balances'][0]['approved_opening']);
        file_put_contents($this->directory.'/manifest.json',json_encode($manifest));$this->assertSame(1,\Illuminate\Support\Facades\Artisan::call('erp:source-inventory',['manifest'=>$this->directory.'/manifest.json']));$this->assertSame(0,\Illuminate\Support\Facades\DB::table('erp_ledger')->count());
        $this->assertSame(1,\Illuminate\Support\Facades\Artisan::call('erp:source-inventory',['manifest'=>'https://example.invalid/source.json']));
    }
}
