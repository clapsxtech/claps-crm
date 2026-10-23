<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAdministrator
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->guest(route('login'));
        }

        $isAdministrator = (int) $user->getKey() === 1
            || strtolower((string) $user->user_type) === 'admin'
            || $user->hasRole('Admin')
            || $user->can('administration');

        abort_unless($isAdministrator, 403, 'Administrator access is required.');

        return $next($request);
    }
}
