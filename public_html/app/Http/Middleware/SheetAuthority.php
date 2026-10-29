<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** One writer during the bridge: operational changes continue in Sheets. */
class SheetAuthority
{
    public function handle(Request $request, Closure $next)
    {
        $authority = config('sheet_transition.authority','application');
        abort_unless(in_array($authority,['application','sheets'],true),503,'Invalid data authority configuration.');
        if ($authority !== 'sheets') return $next($request);
        // Hide the legacy deletion confirmation as well as blocking the actual DELETE request.
        if ($request->routeIs('delete')) abort(409,'Sheets are authoritative during the transition.');
        if ($request->isMethodSafe()) return $next($request);
        // Explicit read/auth/account-management allowlist. New mutation routes fail closed.
        // Laravel UI names GET /login, but its POST /login route is unnamed.
        $loginSubmission = $request->isMethod('post') && $request->path() === 'login';
        $allowed = $loginSubmission || $request->routeIs('login','logout','password.*','two-factor.*','erp.accounts.update','erp.teams.*','erp.onboard','erp.migration.preview','users.*','roles.*','maintenance.*',
            'get-teacher-dob','get-teachers','get-teacher','get-students','get-student','get-students-mark','student-attendance-report','attendanceReport','markreport','studentinfom','studentinforesult');
        if (!$allowed && !$request->is('admin/update-settings')) abort(409,'Sheets are authoritative during the transition. Make operational changes in the original Sheet.');
        return $next($request);
    }
}
