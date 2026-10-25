<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function showSuperadminLogin() { return view('auth.login', ['portal' => 'superadmin']); }
    public function showStaffLogin() { return view('auth.login', ['portal' => 'staff']); }

    protected function validateLogin(\Illuminate\Http\Request $request)
    {
        $request->validate(['email' => 'required|string', 'password' => 'required|string', 'portal' => 'nullable|in:superadmin,staff']);
    }

    protected function authenticated($request, $user)
    {
        $policy = new \App\Modules\IdentityAccess\Services\SiteAccessPolicy();
        $superadmin = $policy->isAdministrator($user);
        if (!$policy->isSiteMember($user)
            || ($request->input('portal') === 'superadmin' && !$superadmin)
            || ($request->input('portal') === 'staff' && $superadmin)) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'This account cannot access the selected login.']);
        }
        if ($user->two_factor_secret) {
            $this->guard()->logout();
            $request->session()->forget('url.intended');
            $request->session()->put('two_factor_pending',['id'=>$user->id,'expires'=>time()+300,'password_hash'=>$user->password]);
            return redirect()->route('two-factor.challenge');
        }
        if ($superadmin) {
            $request->session()->forget('url.intended');
            return redirect()->route('erp.index');
        }
        if (\Illuminate\Support\Facades\DB::table('erp_memberships')->where('user_id',$user->id)->where('role','teacher')->exists()
            || \Illuminate\Support\Facades\DB::table('erp_teachers')->where('user_id',$user->id)->exists()) {
            $request->session()->forget('url.intended');
            return redirect()->route('erp.teacher.index');
        }
    }

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
}
 
