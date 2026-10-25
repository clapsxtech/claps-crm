<?php
namespace Tests\Feature;
use App\Modules\IdentityAccess\Services\TwoFactor;
use Illuminate\Support\Facades\Crypt;
use OTPHP\TOTP;
use Tests\TestCase;
class TwoFactorWorkflowTest extends TestCase
{
    public function test_enrollment_challenge_recovery_and_replay_protection(): void
    {
        $user=$this->createAdmin();$this->actingAs($user);
        $this->get(route('account.security'))->assertOk();
        $this->post(route('two-factor.setup'),['current_password'=>'Local-Testing-Only-2026'])->assertRedirect();
        $pending=session('two_factor_setup');$secret=Crypt::decryptString($pending['secret']);
        $this->assertNotSame($secret,$pending['secret']);
        $this->get(route('account.security'))->assertOk()->assertSee($secret);
        $code=TOTP::createFromSecret($secret)->at(now()->timestamp);
        $this->post(route('two-factor.enable'),['current_password'=>'Local-Testing-Only-2026','code'=>$code])->assertRedirect();
        $codes=json_decode(Crypt::decryptString(session('two_factor_recovery_display')),true);
        $this->assertCount(8,$codes);
        $this->assertNotSame($secret,$user->fresh()->two_factor_secret);
        $this->assertStringNotContainsString($codes[0],$user->fresh()->two_factor_recovery_codes);
        $this->get(route('account.security'))->assertOk()->assertSee($codes[0]);
        $this->get(route('account.security'))->assertDontSee($codes[0]);
        $this->post('/logout');
        $this->post('/login',['email'=>$user->email,'password'=>'Local-Testing-Only-2026','portal'=>'superadmin'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();$this->get('/')->assertRedirect('/login');
        $this->postJson(route('two-factor.verify'),['code'=>$code])->assertUnprocessable(); // Enrollment step cannot be replayed.
        $this->post(route('two-factor.verify'),['code'=>$codes[0]])->assertRedirect('/');$this->assertAuthenticatedAs($user);
        $this->assertFalse((new TwoFactor())->consume($user->id,$codes[0]));
        $this->post(route('two-factor.disable'),['current_password'=>'Local-Testing-Only-2026','code'=>$codes[1]])->assertRedirect();
        $this->assertNull($user->fresh()->two_factor_secret);
    }
    public function test_password_change_or_expired_challenge_prevents_login(): void
    {
        $user=$this->createAdmin();$secret=TOTP::generate()->getSecret();
        $user->forceFill(['two_factor_secret'=>Crypt::encryptString($secret),'two_factor_recovery_codes'=>json_encode([hash('sha256','test-recovery-code')])])->save();
        $this->withSession(['two_factor_pending'=>['id'=>$user->id,'expires'=>time()-1,'password_hash'=>$user->password]])->postJson(route('two-factor.verify'),['code'=>'test-recovery-code'])->assertUnprocessable();
        $this->withSession(['two_factor_pending'=>['id'=>$user->id,'expires'=>time()+300,'password_hash'=>'old-hash']])->postJson(route('two-factor.verify'),['code'=>'test-recovery-code'])->assertUnprocessable();
        $this->assertGuest();
    }
}
