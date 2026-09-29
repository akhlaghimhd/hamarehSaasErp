<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MfaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected MfaService $mfa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email'  => 'mfa.user@example.com',
            'mobile' => '09120001122',
            'status' => 1,
        ]);

        UserCredential::create([
            'credential_id'       => (string) Str::uuid(),
            'user_id'             => $this->user->user_id,
            'password_hash'       => bcrypt('Secure9!'),
            'must_set_password'   => false,
            'authentication_type' => 1,
            'is_verified'         => true,
            'two_factor_enabled'  => false,
            'failed_login_count'  => 0,
        ]);

        $this->user->load('credential');
        $this->mfa = app(MfaService::class);
    }

    #[Test]
    public function begin_enable_returns_secret_and_otpauth_uri(): void
    {
        $result = $this->mfa->beginEnable($this->user->fresh(['credential']));

        $this->assertNotEmpty($result['secret']);
        $this->assertStringStartsWith('otpauth://totp/', $result['otpauth_uri']);
        $this->assertFalse((bool) $this->user->fresh()->credential->two_factor_enabled);
    }

    #[Test]
    public function confirm_enable_with_valid_totp(): void
    {
        $begin = $this->mfa->beginEnable($this->user->fresh(['credential']));
        $code = $this->mfa->verifyTotp($begin['secret'], '000000')
            ? '000000'
            : $this->currentTotp($begin['secret']);

        // Use real current code
        $code = $this->currentTotp($begin['secret']);

        $result = $this->mfa->confirmEnable($this->user->fresh(['credential']), $code);

        $this->assertTrue($result['enabled']);
        $this->assertCount(MfaService::RECOVERY_COUNT, $result['recovery_codes']);

        $cred = $this->user->fresh()->credential;
        $this->assertTrue((bool) $cred->two_factor_enabled);
        $this->assertNotNull($cred->two_factor_confirmed_at);
        $this->assertTrue($this->mfa->isRequired($this->user->fresh(['credential'])));
    }

    #[Test]
    public function confirm_enable_rejects_bad_code(): void
    {
        $this->mfa->beginEnable($this->user->fresh(['credential']));

        $this->expectException(HttpException::class);
        $this->mfa->confirmEnable($this->user->fresh(['credential']), '000000');
    }

    #[Test]
    public function disable_with_totp_clears_flags(): void
    {
        $begin = $this->mfa->beginEnable($this->user->fresh(['credential']));
        $code = $this->currentTotp($begin['secret']);
        $this->mfa->confirmEnable($this->user->fresh(['credential']), $code);

        $code2 = $this->currentTotp($begin['secret']);
        $this->mfa->disable($this->user->fresh(['credential']), $code2);

        $cred = $this->user->fresh()->credential;
        $this->assertFalse((bool) $cred->two_factor_enabled);
        $this->assertNull($cred->totp_secret);
        $this->assertNull($cred->two_factor_confirmed_at);
    }

    #[Test]
    public function recovery_code_is_single_use(): void
    {
        $begin = $this->mfa->beginEnable($this->user->fresh(['credential']));
        $code = $this->currentTotp($begin['secret']);
        $result = $this->mfa->confirmEnable($this->user->fresh(['credential']), $code);

        $recovery = $result['recovery_codes'][0];
        $cred = $this->user->fresh()->credential;

        $this->assertTrue($this->mfa->verifyAny($cred, $recovery));

        $cred = $this->user->fresh()->credential;
        $this->assertFalse($this->mfa->verifyAny($cred, $recovery));
    }

    private function currentTotp(string $secret): string
    {
        // Reuse private logic via public verify loop — generate by reflecting period
        $svc = $this->mfa;
        $ref = new \ReflectionClass($svc);
        $hotp = $ref->getMethod('hotp');
        $hotp->setAccessible(true);
        $counter = intdiv(time(), MfaService::PERIOD);

        return $hotp->invoke($svc, $secret, $counter);
    }
}
