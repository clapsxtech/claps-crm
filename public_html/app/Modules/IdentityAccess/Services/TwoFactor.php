<?php
namespace App\Modules\IdentityAccess\Services;
use App\Models\User;
use Illuminate\Support\Facades\{Crypt,DB};
use OTPHP\TOTP;
class TwoFactor
{
    public function consume(int $id,string $code): bool
    {
        return DB::transaction(function()use($id,$code){
            $user=User::where('id',$id)->lockForUpdate()->first();
            if(!(new SiteAccessPolicy())->isSiteMember($user)||!$user->two_factor_secret)return false;
            $codes=json_decode($user->two_factor_recovery_codes??'[]',true);
            $hash=hash('sha256',$code);
            foreach($codes as $key=>$stored)if(hash_equals($stored,$hash)){
                unset($codes[$key]);$user->two_factor_recovery_codes=json_encode(array_values($codes));$user->save();return true;
            }
            if(!preg_match('/^\d{6}$/',$code))return false;
            $otp=TOTP::createFromSecret(Crypt::decryptString($user->two_factor_secret));
            $step=intdiv(now()->timestamp,30);
            foreach([$step,$step-1,$step+1] as $candidate){
                if($candidate>(int)($user->two_factor_last_step??-1) && hash_equals($otp->at($candidate*30),$code)){
                    $user->two_factor_last_step=$candidate;$user->save();return true;
                }
            }
            return false;
        });
    }
}
