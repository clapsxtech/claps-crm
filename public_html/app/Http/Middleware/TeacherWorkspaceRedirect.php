<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherWorkspaceRedirect
{
    public function handle(Request $request, Closure $next)
    {
        $destinations=['/'=>'overview','/timetableReport'=>'timetable','/timetable'=>'timetable','/attendance'=>'attendance','/attendanceReport'=>'reports','/student-attendance-report'=>'reports','/resultform'=>'results','/markreport'=>'reports'];
        if($request->isMethod('get') && isset($destinations[$request->getPathInfo()]) && $request->user()
            && !(new \App\Modules\IdentityAccess\Services\SiteAccessPolicy())->isAdministrator($request->user())
            && (DB::table('erp_memberships')->where('user_id',$request->user()->id)->where('role','teacher')->exists()
                || DB::table('erp_teachers')->where('user_id',$request->user()->id)->exists())) {
            return redirect()->route('erp.teacher.index',['section'=>$destinations[$request->getPathInfo()]]);
        }
        $response=$next($request);
        if($request->is('erp/teacher*')) $response->headers->set('Cache-Control','no-store, private');
        return $response;
    }
}
