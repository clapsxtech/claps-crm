<?php
namespace App\Http\Middleware;

use Closure;
use App\Modules\Erp\Services\{Access,TeamPermissions};
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Illuminate\Http\Request;

class TeamAuthorization
{
    public function handle(Request $request, Closure $next)
    {
        $user=$request->user();
        if (!$user) return $next($request);
        (new SiteAccessPolicy())->assertSiteMember($user);
        if (!$request->route('unit')) return $next($request);
        $unit=(int)$request->route('unit');
        (new Access())->unit($user,$unit,!$request->isMethod('get'));
        $permissions=new TeamPermissions(); $route=$request->route()->getName();
        [$kind,$value]=match($route) {
            'erp.business'=>['page',(string)$request->route('page','dashboard')],
            'erp.mutate','erp.business.mutate'=>['action',(string)$request->route('action')],
            'erp.lookup'=>['lookup',(string)$request->input('type')],
            'erp.export'=>['export',(string)$request->route('dataset')],
            'erp.academics.export'=>['export','marks'],
            'erp.receipt','erp.request.print'=>['page','money'],
            'erp.reconciliation'=>['page','migration'],
            // These legacy composite screens contain financial and personal histories.
            'erp.workspace','erp.student-profile'=>['detail','all'],
            default=>['unknown',''],
        };
        abort_unless($permissions->allows($user,$unit,$kind,$value),403,'Your team role does not allow this operation.');
        return $next($request);
    }
}
