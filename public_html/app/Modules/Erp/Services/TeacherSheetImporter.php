<?php

namespace App\Modules\Erp\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class TeacherSheetImporter
{
    public function files(string $directory): array
    {
        $files=glob(rtrim($directory,'/').'/*.json'); sort($files);
        if (!$files) throw new RuntimeException('No teacher snapshot JSON files found.');
        return $files;
    }
    public function preview(string $directory): array
    {
        $sources=[]; $counts=['application'=>0,'registration'=>0,'invalid_contact'=>0];
        foreach ($this->files($directory) as $file) {
            $rows=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
            if (!array_is_list($rows)) throw new RuntimeException('Invalid snapshot rows.');
            foreach ($rows as $r) {
                if (!in_array($r['kind']??'', ['application','registration'],true) || empty($r['source_key']) || empty($r['name'])) throw new RuntimeException('Missing source identity.');
                $counts[$r['kind']]++;
                if (!TeacherRecruitment::phone($r['phone']??null)) $counts['invalid_contact']++;
            }
            $sources[basename($file)]=hash_file('sha256',$file);
        }
        return ['digest'=>hash('sha256',json_encode($sources,JSON_THROW_ON_ERROR)),'counts'=>$counts,'files'=>count($sources),'mode'=>'preview'];
    }
    public function execute(string $directory, int $unit, int $actor, ?callable $progress = null): array
    {
        $user=\App\Models\User::findOrFail($actor);
        (new Access())->unit($user,$unit);
        abort_unless((new TeacherRecruitment())->canManage($user,$unit),403);
        $preview=$this->preview($directory);
        $counts=['new_profiles'=>0,'linked_teachers'=>0,'source_rows'=>0,'already_imported'=>0,'review'=>0];
        // Registration first: one profile can then accumulate its applications.
        $files=$this->files($directory);
        usort($files,fn($a,$b)=>(str_starts_with(basename($b),'registration')<=>str_starts_with(basename($a),'registration')) ?: strcmp($a,$b));
        foreach ($files as $file) {
            $rows=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
            foreach (array_chunk($rows,200) as $chunk) {
                $part=DB::transaction(function () use ($chunk) {
                    $part=['new_profiles'=>0,'linked_teachers'=>0,'source_rows'=>0,'already_imported'=>0,'review'=>0];
                    foreach ($chunk as $row) {
                        if (DB::table('erp_teacher_candidate_sources')->where('source_key',$row['source_key'])->exists()) { $part['already_imported']++; continue; }
                        $phone=TeacherRecruitment::phone($row['phone']??null);
                        $name=TeacherRecruitment::name($row['name']);
                        $business=(int)config('erp.business_id');
                        $key=hash('sha256',$phone ? $phone.':'.$name : 'source:'.$row['source_key']);
                        $c=DB::table('erp_teacher_candidates')->where('business_id',$business)->where('identity_key',$key)->first();
                        if($c?->merged_into_id)$c=(new TeacherRecruitment())->candidate($c->merged_into_id);
                        $teacher=null; $conflict=false;
                        $code=trim($row['code']??'');
                        if ($code && preg_match('/^CT[0-9]+$/i',$code)) {
                            $code=strtoupper($code);
                            $teacher=DB::table('erp_teachers')->where('code',$code)->first();
                            if ($teacher && TeacherRecruitment::name($teacher->name)!==$name) { $teacher=null; $conflict=true; }
                            $byCode=DB::table('erp_teacher_candidates')->where('business_id',$business)->where('teacher_code',$code)->first();
                            if ($byCode && (!$c || $c->id!==$byCode->id)) {
                                if (TeacherRecruitment::name($byCode->name)===$name && $phone && $byCode->phone===$phone) $c=$byCode;
                                else { $conflict=true; $teacher=null; }
                            }
                        } else $code=null;
                        if (!$c && $phone && DB::table('erp_teacher_candidates')->where('business_id',$business)->where('phone',$phone)->exists()) $conflict=true;
                        if ($teacher && DB::table('erp_teacher_candidates')->where('teacher_id',$teacher->id)->when($c,fn($q)=>$q->where('id','!=',$c->id))->exists()) { $teacher=null;$conflict=true; }
                        $values=[];
                        foreach (['qualification','subjects','grades','syllabuses','medium','availability','source_status'] as $field) if (!empty($row[$field]) && (!$c || !$c->$field || ($row['kind']==='registration' && !$c->registered_at))) $values[$field]=mb_substr($row[$field],0,8000);
                        $email=trim($row['email']??'');
                        if (filter_var($email,FILTER_VALIDATE_EMAIL) && (!$c || !$c->email)) $values['email']=$email;
                        if (!$c) {
                            $stage=($conflict || !$phone)?'REVIEW':($row['kind']==='registration'?'REGISTERED':'APPLIED');
                            $id=DB::table('erp_teacher_candidates')->insertGetId($values+['business_id'=>$business,'identity_key'=>$key,'name'=>mb_substr($row['name'],0,255),'phone'=>$phone,'teacher_code'=>$code,'teacher_id'=>$teacher?->id,'stage'=>$stage,'registered_at'=>$row['kind']==='registration'?now():null,'created_at'=>now(),'updated_at'=>now()]);
                            $part['new_profiles']++; if ($stage==='REVIEW') $part['review']++;
                            if ($teacher) $part['linked_teachers']++;
                        } else {
                            $id=$c->id;
                            if ($row['kind']==='registration' && !$c->registered_at) {
                                $values['registered_at']=now();
                                if (!in_array($c->stage,['APPROVED','REJECTED','REVIEW'],true)) $values['stage']='REGISTERED';
                            }
                            if ($conflict) { $values['stage']='REVIEW'; $part['review']++; }
                            if ($teacher && !$c->teacher_id && !$conflict) { $values['teacher_id']=$teacher->id;$part['linked_teachers']++; }
                            if ($code && !$c->teacher_code) $values['teacher_code']=$code;
                            if ($values) DB::table('erp_teacher_candidates')->where('id',$id)->update($values+['updated_at'=>now()]);
                        }
                        // Allowlist: never persist banking details, ID proof, family or home address.
                        $payload=array_intersect_key($row,array_flip(['name','code','phone','email','qualification','subjects','grades','syllabuses','medium','availability','source_status','submitted_at','band','category','teacher_type']));
                        DB::table('erp_teacher_candidate_sources')->insert(['candidate_id'=>$id,'source_key'=>$row['source_key'],'kind'=>$row['kind'],'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()]);
                        $part['source_rows']++;
                    }
                    return $part;
                });
                foreach ($part as $key=>$value) $counts[$key]+=$value;
            }
            if ($progress) $progress(basename($file),$counts);
        }
        DB::transaction(fn()=>Audit::record($unit,$actor,'teachers.snapshot_imported','teacher_snapshot',0,['digest'=>$preview['digest'],'counts'=>$counts]));
        return $counts+['digest'=>$preview['digest']];
    }
}
