<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Contracts\SmsSenderInterface;
use App\Modules\IdentityCore\Models\IdentityLoginOtp;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Services\OtpLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OtpLoginServiceTest extends TestCase
{
    use RefreshDatabase;

    private object $smsFake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->smsFake = new class implements SmsSenderInterface {
            public array $sent = [];

            public function send(string $mobile, string $message): void
            {
                $this->sent[] = ['mobile' => $mobile, 'message' => $message];
            }
        };

        $this->app->instance(SmsSenderInterface::class, $this->smsFake);
    }

    private function makeActiveUser(string $mobile = '09121234567'): User
    {
        $user = User::factory()->create([
            'mobile' => $mobile,
            'status' => 1,
        ]);

        UserCredential::create([
            'credential_id'       => (string) Str::uuid(),
            'user_id'             => $user->user_id,
            'password_hash'       => null,
            'must_set_password'   => true,
            'authentication_type' => 1,
            'is_verified'         => false,
            'two_factor_enabled'  => false,
            'failed_login_count'  => 0,
        ]);

        return $user->fresh();
    }

    private function service(): OtpLoginService
    {
        return $this->app->make(OtpLoginService::class);
    }

    #[Test]
    public function request_otp_does_not_resend_when_active_code_exists(): void
    {
        $user = $this->makeActiveUser();
        $svc = $this->service();

        $first = $svc->requestOtp($user->mobile, '127.0.0.1', false);
        $this->assertArrayHasKey('expires_in', $first);
        $this->assertSame(OtpLoginService::TTL_SECONDS, $first['expires_in']);
        $this->assertCount(1, $this->smsFake->sent);

        try {
            $svc->requestOtp($user->mobile, '127.0.0.1', false);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertStringContainsString('هنوز معتبر است', $e->getMessage());
        }

        $this->assertSame(1, IdentityLoginOtp::where('mobile', $user->mobile)->count());
        $this->assertCount(1, $this->smsFake->sent);
    }

    #[Test]
    public function force_resend_within_cooldown_is_blocked_with_ten_minute_message(): void
    {
        $user = $this->makeActiveUser('09129876543');
        $svc = $this->service();

        $svc->requestOtp($user->mobile, '127.0.0.1', false);

        try {
            $svc->requestOtp($user->mobile, '127.0.0.1', true);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertStringContainsString('۱۰ دقیقه', $e->getMessage());
        }

        $this->assertSame(
            1,
            IdentityLoginOtp::where('mobile', $user->mobile)->whereNull('consumed_at')->count()
        );
        $this->assertCount(1, $this->smsFake->sent);
    }

    #[Test]
    public function same_code_can_be_used_multiple_times_until_expiry(): void
    {
        $user = $this->makeActiveUser('09121112233');
        $plain = '123456';

        IdentityLoginOtp::create([
            'otp_id'        => (string) Str::uuid(),
            'mobile'        => $user->mobile,
            'code_hash'     => Hash::make($plain),
            'expires_at'    => now()->addMinutes(10),
            'last_sent_at'  => now(),
            'consumed_at'   => null,
            'attempt_count' => 0,
            'request_ip'    => '127.0.0.1',
        ]);

        $svc = $this->service();

        $first = $svc->verifyOtpForPasswordReset($user->mobile, $plain);
        $this->assertSame($user->user_id, $first->user_id);

        // Same code still works (multi-use)
        $second = $svc->verifyOtpForPasswordReset($user->mobile, $plain);
        $this->assertSame($user->user_id, $second->user_id);

        $this->assertSame(
            1,
            IdentityLoginOtp::where('mobile', $user->mobile)->whereNull('consumed_at')->count()
        );
    }

    #[Test]
    public function issuing_new_code_invalidates_previous_codes(): void
    {
        $user = $this->makeActiveUser('09123334455');
        $svc = $this->service();

        $svc->requestOtp($user->mobile, '127.0.0.1', false);
        $oldId = IdentityLoginOtp::where('mobile', $user->mobile)->whereNull('consumed_at')->value('otp_id');
        $this->assertNotNull($oldId);

        // Travel past cooldown
        $this->travel(OtpLoginService::RESEND_COOLDOWN_SECONDS + 1)->seconds();

        $svc->requestOtp($user->mobile, '127.0.0.1', true);

        $old = IdentityLoginOtp::find($oldId);
        $this->assertNotNull($old->consumed_at);

        $this->assertSame(
            1,
            IdentityLoginOtp::where('mobile', $user->mobile)->whereNull('consumed_at')->count()
        );
        $this->assertCount(2, $this->smsFake->sent);
    }
}
