<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Erp\Services\Audit;
use App\Modules\IdentityAccess\Services\{SiteAccessPolicy,TwoFactor};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Crypt,DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OTPHP\TOTP;
class TwoFactorController extends Controller
{
    public function challenge(Request $request)
    {
        abort_unless($request->session()->has('two_factor_pending'),403);
        return response()->view('auth.two-factor')->header('Cache-Control','no-store');
    }
    public function verify(Request $request)
    {
        $v=$request->validate(['code'=>'required|string|max:100']);$pending=$request->session()->get('two_factor_pending');
        $user=$pending?User::find($pending['id']):null;
        if(!$user || $pending['expires']<time() || !hash_equals($pending['password_hash'],$user->password) || !(new TwoFactor())->consume($user->id,$v['code']))throw ValidationException::withMessages(['code'=>'Invalid or expired code. Try a new code or a recovery code.']);
        $request->session()->forget('two_factor_pending');Auth::login($user);$request->session()->regenerate();
        $teacher=!(new SiteAccessPolicy())->isAdministrator($user) && (DB::table('erp_memberships')->where('user_id',$user->id)->where('role','teacher')->exists() || DB::table('erp_teachers')->where('user_id',$user->id)->exists());
        return redirect()->route($teacher?'erp.teacher.index':'erp.index');
    }
    public function settings(Request $request)
    {
        $pending=$request->session()->get('two_factor_setup');
        $secret=$pending && $pending['expires']>time()?Crypt::decryptString($pending['secret']):null;
        $encrypted=$request->session()->pull('two_factor_recovery_display');
        $codes=$encrypted?json_decode(Crypt::decryptString($encrypted),true):[];
        return response()->view('erp.security',compact('secret','codes'))->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
    public function setup(Request $request)
    {
        $this->password($request);abort_if($request->user()->two_factor_secret,422,'Two-factor authentication is already enabled.');
        $request->session()->put('two_factor_setup',['secret'=>Crypt::encryptString(TOTP::generate()->getSecret()),'expires'=>time()+600]);
        return redirect()->route('account.security');
    }
    public function enable(Request $request)
    {
        $this->password($request);$v=$request->validate(['code'=>'required|digits:6']);$pending=$request->session()->get('two_factor_setup');
        if(!$pending || $pending['expires']<time())throw ValidationException::withMessages(['code'=>'Start setup again; this setup has expired.']);
        $secret=Crypt::decryptString($pending['secret']);$otp=TOTP::createFromSecret($secret);
        if(!$otp->verify($v['code'],now()->timestamp,0))throw ValidationException::withMessages(['code'=>'Enter the current code from your authenticator.']);
        $codes=array_map(fn()=>bin2hex(random_bytes(12)),range(1,8));
        DB::transaction(function()use($request,$pending,$codes){
            $user=User::where('id',$request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($user->two_factor_secret,422,'Two-factor authentication is already enabled.');
            $user->two_factor_secret=$pending['secret'];$user->two_factor_recovery_codes=json_encode(array_map(fn($code)=>hash('sha256',$code),$codes));$user->two_factor_last_step=intdiv(now()->timestamp,30);$user->remember_token=Str::random(60);$user->save();
            DB::table('sessions')->where('user_id',$user->id)->where('id','!=',$request->session()->getId())->delete();
            Audit::record(null,$user->id,'account.two_factor_enabled','user',$user->id,[]);
        });
        $request->session()->forget('two_factor_setup');$request->session()->put('two_factor_recovery_display',Crypt::encryptString(json_encode($codes)));
        return redirect()->route('account.security')->with('status','Two-factor authentication enabled. Save your recovery codes now.');
    }
    public function disable(Request $request)
    {
        $this->password($request);$request->validate(['code'=>'required|string|max:100']);
        DB::transaction(function()use($request){
            if(!(new TwoFactor())->consume($request->user()->id,$request->code))throw ValidationException::withMessages(['code'=>'Enter a fresh authenticator or recovery code.']);
            User::where('id',$request->user()->id)->update(['two_factor_secret'=>null,'two_factor_recovery_codes'=>null,'two_factor_last_step'=>null,'remember_token'=>Str::random(60)]);
            DB::table('sessions')->where('user_id',$request->user()->id)->where('id','!=',$request->session()->getId())->delete();
            Audit::record(null,$request->user()->id,'account.two_factor_disabled','user',$request->user()->id,[]);
        });
        return redirect()->route('account.security')->with('status','Two-factor authentication disabled.');
    }
    private function password(Request $request): void
    {
        $request->validate(['current_password'=>'required|string']);
        if(!Hash::check($request->current_password,$request->user()->password))throw ValidationException::withMessages(['current_password'=>'Your current password is incorrect.']);
    }
}
