<?php
namespace App\Modules\Erp\Services;

/** Read-only source-shape diagnostics. Deliberately cannot construct importable rows. */
class SourceCsvInventory
{
    public function decimalPaise(string $value): int { return $this->scaled($value,100,2); }
    public function decimalMinutes(string $value): int { $minutes=$this->scaled($value,60,4);if($minutes<0)throw new \InvalidArgumentException('Negative hours');return $minutes; }
    private function scaled(string $value,int $scale,int $precision): int
    {
        if(!preg_match('/^(-?)(0|[1-9][0-9]{0,10})(?:\.([0-9]{1,'.$precision.'}))?$/D',$value,$m))throw new \InvalidArgumentException('Noncanonical decimal value');
        $fraction=$m[3]??'';$denominator=10**strlen($fraction);$numerator=((int)$m[2]*$denominator+(int)$fraction)*$scale;
        if($numerator%$denominator!==0)throw new \InvalidArgumentException('Value does not represent an exact integer unit');
        $result=intdiv($numerator,$denominator);return $m[1]==='-'?-$result:$result;
    }
    private function column(string $letters): int
    {
        if(!preg_match('/^[A-Z]{1,3}$/D',$letters))throw new \InvalidArgumentException('Invalid physical column');
        $n=0;foreach(str_split($letters) as $c)$n=$n*26+ord($c)-64;return $n;
    }
    private function date($value): bool { return is_string($value)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)&&($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value))&&$d->format('Y-m-d')===$value; }
    public function inspect(array $manifest,string $directory): array
    {
        $issues=[];$issue=function(string $code,?string $file=null,?int $row=null)use(&$issues){$issues[]=['code'=>$code,'file'=>$file,'row'=>$row];};
        $result=['schema_version'=>1,'can_import'=>false,'writes'=>0,'inventory'=>[],'attendance'=>['physical_records'=>0,'unique_records'=>0,'mirror_records'=>0,'actual_minutes'=>0],'observed_rates'=>[],'observed_balances'=>[],'issues'=>[]];
        if(($manifest['schema_version']??null)!==1||!is_string($manifest['source_id']??null)||trim($manifest['source_id'])===''||strlen($manifest['source_id'])>128||!is_string($manifest['exported_at']??null)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D',$manifest['exported_at'])||!$this->date(substr($manifest['exported_at'],0,10))||(int)substr($manifest['exported_at'],11,2)>23||(int)substr($manifest['exported_at'],14,2)>59||(int)substr($manifest['exported_at'],17,2)>59||!is_array($manifest['sources']??null)||count($manifest['sources'])<1||count($manifest['sources'])>100||array_keys($manifest['sources'])!==range(0,count($manifest['sources'])-1)){ $result['issues']=[['code'=>'invalid_manifest_schema','file'=>null,'row'=>null]]; return $result; }
        $root=realpath($directory);if(!$root)throw new \InvalidArgumentException('Local source directory required');
        if(!$this->date($manifest['cutoff_date']??null))$issue('missing_or_invalid_approved_cutoff');
        if(($manifest['student_openings_approved']??false)!==true)$issue('student_opening_balances_not_approved');
        $issue('teacher_opening_balances_unsupported');
        $seen=[];$invalidAttendance=false;$files=[];$totalRows=0;
        foreach($manifest['sources'] as $source){
            $file=$source['file']??null;
            if(!is_array($source)){$issue('invalid_source_schema');continue;}
            $declared=$source['rows']??null;if(!is_int($declared)||$declared<1||$declared>10000||($totalRows+=$declared)>50000){$issue('source_row_limit',is_string($file)?$file:null);continue;}
            if(!is_string($file)||basename($file)!==$file||strpos($file,':')!==false||isset($files[$file])){$issue('invalid_or_duplicate_source_file');continue;}$files[$file]=true;
            $path=realpath($root.DIRECTORY_SEPARATOR.$file);
            if(!$path||dirname($path)!==$root||!is_file($path)||filesize($path)>5*1024*1024){$issue('invalid_local_source_file',$file);continue;}
            if(!preg_match('/^([A-Z]{1,3})([1-9][0-9]*):([A-Z]{1,3})([1-9][0-9]*)$/D',$source['range']??'',$range)||empty($source['book'])||empty($source['sheet'])||empty($source['timezone'])||!in_array($source['timezone']==='Asia/Calcutta'?'Asia/Kolkata':$source['timezone'],\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),true)||!is_int($source['rows']??null)||!is_array($source['columns']??null)||!preg_match('/^[a-f0-9]{64}$/D',$source['sha256']??'')){$issue('invalid_source_schema',$file);continue;}
            if(!hash_equals($source['sha256'],hash_file('sha256',$path))){$issue('source_digest_mismatch',$file);continue;}
            $start=$this->column($range[1]);$width=$this->column($range[3])-$start+1;$kind=$source['kind']??'inventory';
            if($width<1||(int)$range[4]-(int)$range[2]+1!==$source['rows']){$issue('range_row_count_mismatch',$file);continue;}
            $mapped=[];try{foreach($source['columns'] as $key=>$column){$index=$this->column($column)-$start;if($index<0||$index>=$width)throw new \InvalidArgumentException();$mapped[$key]=$index;}}catch(\Throwable $e){$issue('column_outside_range',$file);continue;}
            $required=['attendance'=>['attendance_id','teacher_id','student_id','date','hours','unit'],'master'=>['student_id','teacher_id','student_rate','teacher_rate'],'due'=>['prior','received','charges','current'],'registration'=>['student_id']];
            if(!in_array($kind,['inventory','attendance','master','due','registration','timetable'],true)||array_diff($required[$kind]??[],array_keys($mapped))){$issue('missing_required_physical_columns',$file);continue;}
            $raw=file_get_contents($path);if(strpos($raw,'\\r\\n')!==false||strpos($raw,"\0")!==false){$issue('malformed_csv_encoding',$file);continue;}
            $handle=fopen($path,'r');$count=0;$header=$source['header_rows']??0;
            if(!is_int($header)||$header<0||$header>$source['rows']){$issue('invalid_header_count',$file);fclose($handle);continue;}
            while(($cells=fgetcsv($handle,0,',','"',''))!==false){$count++;if($count>10000){$issue('source_row_limit',$file);break;}if(count($cells)>$width){$issue('too_many_physical_columns',$file,$count);continue;}if($count<=$header)continue;
                $row=[];foreach($mapped as $key=>$index)$row[$key]=array_key_exists($index,$cells)&&$cells[$index]!==''?$cells[$index]:null;
                try{
                    foreach(['student_id','teacher_id','attendance_id'] as $identityKey)if(array_key_exists($identityKey,$mapped)&&(!is_string($row[$identityKey])||trim($row[$identityKey])===''||strlen($row[$identityKey])>128))throw new \InvalidArgumentException();
                    if($kind==='attendance'){
                        $result['attendance']['physical_records']++;
                        foreach($required[$kind] as $key)if($row[$key]===null)throw new \InvalidArgumentException();
                        if(!$this->date($row['date']))throw new \InvalidArgumentException();$minutes=$this->decimalMinutes($row['hours']);if($minutes<=0||$minutes>1440)throw new \InvalidArgumentException();
                        $identity=$row['attendance_id'];$signature=hash('sha256',json_encode([$row['teacher_id'],$row['student_id'],$row['date'],$minutes,$row['unit'],$row['starts_at']??null,$row['status']??null]));
                        if(isset($seen[$identity])){if($seen[$identity]!==$signature){$issue('conflicting_attendance_identity',$file,$count);$invalidAttendance=true;}else $result['attendance']['mirror_records']++;}
                        else{$seen[$identity]=$signature;$result['attendance']['unique_records']++;$result['attendance']['actual_minutes']+=$minutes;}
                        if(empty($row['starts_at']))$issue('attendance_exact_start_missing',$file,$count);
                        if(empty($row['status']))$issue('attendance_delivery_status_missing',$file,$count);
                    }elseif($kind==='master'){
                        $student=$this->decimalPaise($row['student_rate']??'');$teacher=$this->decimalPaise($row['teacher_rate']??'');if($student<0||$teacher<0||$student>100000000||$teacher>100000000)throw new \InvalidArgumentException();
                        $result['observed_rates'][]=['student_rate_paise'=>$student,'teacher_rate_paise'=>$teacher,'approved'=>false];$issue('assignment_dates_status_and_eligibility_require_confirmation',$file,$count);
                    }elseif($kind==='due'){
                        $v=[];foreach(['prior','received','charges','current'] as $k)$v[$k]=$this->decimalPaise($row[$k]??'');
                        $result['observed_balances'][]=$v+['arithmetic_matches'=>$v['prior']+$v['received']-$v['charges']===$v['current'],'approved_opening'=>null];
                        if($v['prior']+$v['received']-$v['charges']!==$v['current'])$issue('balance_arithmetic_mismatch',$file,$count);
                        $issue('balance_cutoff_and_sign_semantics_unapproved',$file,$count);
                    }elseif($kind==='registration'){
                        if(!$this->date($row['registered_on']??null))$issue('registration_date_not_proven',$file,$count);
                        $issue('source_status_mapping_requires_confirmation',$file,$count);
                    }elseif($kind==='timetable'&&isset($row['expected_hours'])){
                        $result['planned_minutes'][]=$this->decimalMinutes($row['expected_hours']);
                    }
                }catch(\InvalidArgumentException $e){$issue('invalid_required_value_or_precision',$file,$count);if($kind==='attendance')$invalidAttendance=true;}
            }fclose($handle);
            if($count!==$source['rows'])$issue('physical_row_count_mismatch',$file);
            $result['inventory'][]=['file'=>$file,'book'=>$source['book'],'sheet'=>$source['sheet'],'range'=>$source['range'],'source_timezone'=>$source['timezone'],'physical_rows'=>$count,'data_rows'=>max(0,$count-$header),'sha256'=>$source['sha256'],'kind'=>$kind];
        }
        // A structural failure anywhere makes this selected bundle incomplete; never publish a plausible partial total.
        $structural=['invalid_or_duplicate_source_file','invalid_local_source_file','invalid_source_schema','source_digest_mismatch','range_row_count_mismatch','column_outside_range','missing_required_physical_columns','malformed_csv_encoding','invalid_header_count','source_row_limit','too_many_physical_columns','physical_row_count_mismatch'];
        $complete=!array_intersect($structural,array_column($issues,'code'));
        $result['inventory_complete']=$complete;
        if($invalidAttendance||!$complete)$result['attendance']['actual_minutes']=null;
        $result['issues']=$issues;$result['source_id']=$manifest['source_id'];$result['source_reconciled']=false;return $result;
    }
}
