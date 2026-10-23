<?php

namespace App\Http\Middleware;

use App\Modules\IdentityAccess\Services\SiteAccessPolicy;
use Closure;
use Illuminate\Http\Request;

class RequireAdministrator
{
    private $access;

    public function __construct(SiteAccessPolicy $access)
    {
        $this->access = $access;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return redirect()->guest(route('login'));
        }

        $this->access->assertAdministrator($request->user());

        return $next($request);
    }
}
