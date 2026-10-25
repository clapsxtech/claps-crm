<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
class ActiveAccount
{
    public function handle(Request $request,Closure $next)
    {
        if($request->user() && !(new SiteAccessPolicy())->isSiteMember($request->user())) {
            // Keep the request authenticated only to return a consistent denial; lifecycle actions delete database sessions.
            abort(403,'This account is inactive or no longer has access.');
        }
        return $next($request);
    }
}
