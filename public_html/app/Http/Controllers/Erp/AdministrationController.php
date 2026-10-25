<?php
namespace App\Http\Controllers\Erp;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Erp\Services\{Administration,OperationalHealth,TeamPermissions};
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdministrationController extends Controller
{
    public function index(Request $request)
    {
        (new SiteAccessPolicy())->assertAdministrator($request->user());
        $teams=DB::table('erp_units')->orderBy('name')->get();
        $request->validate(['q'=>'nullable|string|max:100']);
        $accounts=User::where('business_id',config('erp.business_id'))->whereNull('deleted_at')
            ->when($request->filled('q'),fn($q)=>$q->where(function($w)use($request){$w->where('name','like','%'.$request->q.'%')->orWhere('email','like','%'.$request->q.'%');}))
            ->orderBy('name')->paginate(20)->withQueryString();
        $memberships=DB::table('erp_memberships as m')->join('erp_units as t','t.id','=','m.unit_id')->whereIn('m.user_id',$accounts->pluck('id'))->select('m.*','t.name')->get()->groupBy('user_id');
        $roles=TeamPermissions::LABELS;
        return view('erp.administration',compact('teams','accounts','memberships','roles'));
    }
    public function team(Request $request,?int $id=null)
    {
        (new Administration())->team($request->user(),$request->all(),$id);
        return back()->with('status','Team saved.');
    }
    public function archive(Request $request,int $id)
    {
        $v=$request->validate(['archived'=>'required|boolean']);
        (new Administration())->archive($request->user(),$id,(bool)$v['archived']);
        return back()->with('status',$v['archived']?'Team archived. Records are preserved.':'Team restored.');
    }
    public function onboard(Request $request)
    {
        $request->validate(['team_ids'=>'required|array|min:1|max:30','team_ids.*'=>'required|integer|distinct','initial_role'=>['required',\Illuminate\Validation\Rule::in(TeamPermissions::ROLES)]]);
        $payload=$request->except('team_ids','initial_role');
        $payload['assignments']=array_map(fn($id)=>['unit_id'=>$id,'role'=>$request->initial_role],$request->team_ids);
        $user=(new Administration())->onboard($request->user(),$payload);
        return redirect()->route('erp.administration')->with('status','Account created and assigned for '.$user->name.'. Share the staff login URL and password privately.');
    }
    public function account(Request $request,int $id)
    {
        (new Administration())->account($request->user(),$id,$request->all());
        return redirect()->route('erp.administration')->with('status','Account updated. Existing database sessions and remembered logins were revoked.');
    }
    public function operations(Request $request)
    {
        (new SiteAccessPolicy())->assertAdministrator($request->user());
        $health=(new OperationalHealth())->inspect();
        if($request->expectsJson())return response()->json($health,$health['status']==='error'?503:200)->header('Cache-Control','no-store');
        $events=DB::table('erp_audit')->orderByDesc('id')->paginate(30,['id','unit_id','actor_id','action','entity_type','entity_id','created_at']);
        return response()->view('erp.operations',compact('health','events'))->header('Cache-Control','no-store');
    }
}
