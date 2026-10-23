<?php
namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Modules\Erp\Services\Operations;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Limited staff portal: assigned tasks and checklist only, never student or money records. */
class OwnTasksController extends Controller
{
    public function __construct() {}
    public function index(Request $request) {
        $user=$request->user(); (new SiteAccessPolicy())->assertSiteMember($user);
        if (!DB::table('erp_memberships')->where('user_id',$user->id)->whereIn('role',['admin','sales','teacher'])->exists()) throw new AuthorizationException('Current staff membership is required.');
        $tasks=DB::table('erp_tasks as t')->join('erp_memberships as m',function($j) { $j->on('m.unit_id','=','t.unit_id')->on('m.user_id','=','t.assigned_to'); })
            ->where('t.assigned_to',$user->id)->where('m.user_id',$user->id)->whereIn('m.role',['admin','sales','teacher'])->orderBy('t.due_on')->orderBy('t.id')
            ->paginate(25,['t.id','t.unit_id','t.title','t.notes','t.due_on','t.status']);
        $items=DB::table('erp_task_items')->whereIn('task_id',$tasks->pluck('id'))->orderBy('id')->get(['id','unit_id','task_id','title','completed_at']);
        // Guard corrupted cross-unit item rows as well as normal task scoping.
        $taskUnits=$tasks->pluck('unit_id','id');
        $items=$items->filter(fn($item)=>isset($taskUnits[$item->task_id]) && (int)$taskUnits[$item->task_id]===(int)$item->unit_id)->groupBy('task_id');
        return view('erp.own-tasks',compact('tasks','items'));
    }
    public function complete(Request $request,int $task) {
        (new SiteAccessPolicy())->assertSiteMember($request->user());
        (new Operations())->completeOwnTask((int)$request->user()->id,$task);
        return redirect()->route('erp.tasks.mine')->with('status','Your assigned task is complete.');
    }
    public function completeItem(Request $request,int $task,int $item) {
        (new SiteAccessPolicy())->assertSiteMember($request->user());
        (new Operations())->completeOwnTaskItem((int)$request->user()->id,$task,$item);
        return redirect()->route('erp.tasks.mine')->with('status','Checklist item completed.');
    }
}
