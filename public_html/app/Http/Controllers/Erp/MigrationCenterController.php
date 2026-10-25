<?php
namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\{MigrationImporter,Audit};
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\ValidationException;

class MigrationCenterController extends Controller
{
    private function document(Request $request): array
    {
        (new SiteAccessPolicy())->assertAdministrator($request->user());
        $request->validate(['source'=>'required|file|max:5120']);
        try {$data=json_decode(file_get_contents($request->file('source')->getRealPath()),true,64,JSON_THROW_ON_ERROR);}
        catch(\Throwable $e){throw ValidationException::withMessages(['source'=>'Upload a valid normalized JSON export.']);}
        if(!is_array($data)||!isset($data['rows'],$data['references'])||!is_array($data['rows'])||!is_array($data['references'])||count($data['rows'])>5000) throw ValidationException::withMessages(['source'=>'Expected rows and references, with at most 5,000 rows.']);
        return $data;
    }
    public function index(Request $request)
    {
        (new SiteAccessPolicy())->assertAdministrator($request->user());
        $report=null;return view('erp.migration-center',compact('report'));
    }
    public function preview(Request $request)
    {
        $data=$this->document($request);$report=(new MigrationImporter())->preview($data['rows'],$data['references']);
        $request->session()->forget('migration_preview');
        if($report['valid'])$request->session()->put('migration_preview',['digest'=>$report['digest'],'actor'=>$request->user()->id,'expires'=>time()+1800,'cutoff'=>$data['references']['cutoff_date'],'database'=>$data['references']['target_database']]);
        if($request->expectsJson())return response()->json($report)->header('Cache-Control','no-store');
        return response()->view('erp.migration-center',compact('report'))->header('Cache-Control','no-store');
    }
    public function execute(Request $request)
    {
        $data=$this->document($request);
        $v=$request->validate(['digest'=>'required|string|size:64','cutoff'=>'required|date_format:Y-m-d','backup_reference'=>'required|string|max:200','current_password'=>'required|string','confirm_import'=>'accepted']);
        if(!Hash::check($v['current_password'],$request->user()->password))throw ValidationException::withMessages(['current_password'=>'Your current password is incorrect.']);
        $preview=$request->session()->get('migration_preview');
        if(!$preview || $preview['actor']!==$request->user()->id || $preview['expires']<time() || !hash_equals($preview['digest'],$v['digest']) || $preview['cutoff']!==$v['cutoff'] || $preview['database']!==$data['references']['target_database'])throw ValidationException::withMessages(['source'=>'Preview this exact export again before importing.']);
        if(count($data['rows'])>100)throw ValidationException::withMessages(['source'=>'Web imports are limited to 100 rows per reviewed snapshot. Use the operator migration workflow for larger imports.']);
        $previous=config('erp.migration_import_enabled');config(['erp.migration_import_enabled'=>true]);
        try {
            $result=DB::transaction(function()use($data,$v,$request){
                $result=(new MigrationImporter())->execute($data['rows'],$data['references'],$v['digest'],$v['cutoff'],$request->user()->id,true);
                Audit::record(null,$request->user()->id,'migration.approved_import','migration_batch',$result['batch_id'],['digest'=>$v['digest'],'backup_reference'=>$v['backup_reference'],'cutoff'=>$v['cutoff']]);
                return $result;
            });
        } finally {config(['erp.migration_import_enabled'=>$previous]);}
        $request->session()->forget('migration_preview');
        if($request->expectsJson())return response()->json($result);
        return redirect()->route('erp.migration.center')->with('status','Import completed and reconciled. Batch #'.$result['batch_id'].'. Historical attendance was not charged again.');
    }
}
