<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Modules\Erp\Services\Audit;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
class ProvisionErpUnit extends Command
{
    protected $signature='erp:provision-unit {code : AA or BB} {admin : Existing site administrator user ID} {--phone=}';
    protected $description='Explicitly provision a business unit and its first administrator; does not import any source data.';
    public function handle()
    {
        $code=strtoupper($this->argument('code'));$user=User::find($this->argument('admin'));
        if(!in_array($code,['AA','BB'],true)||!(new SiteAccessPolicy())->isAdministrator($user)){$this->error('AA/BB and an active existing site administrator are required.');return 1;}
        DB::transaction(function()use($code,$user){$unit=DB::table('erp_units')->where('code',$code)->first();$id=$unit?$unit->id:DB::table('erp_units')->insertGetId(['code'=>$code,'name'=>$code.' Business Unit','phone'=>$this->option('phone'),'timezone'=>'Asia/Kolkata','created_at'=>now(),'updated_at'=>now()]);DB::table('erp_memberships')->updateOrInsert(['unit_id'=>$id,'user_id'=>$user->id],['role'=>'admin','created_at'=>now(),'updated_at'=>now()]);Audit::record($id,$user->id,'unit.provisioned','unit',$id,['code'=>$code]);});
        $this->info('Unit ready. Sign in as the selected administrator and open /erp.');return 0;
    }
}
