<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Contracts\SmsSenderInterface;
use App\Modules\IdentityCore\Models\IdentityLoginOtp;
use App\Modules\IdentityCore\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OtpLoginService
{
    /** Code validity (and resend-block while active). Internal only — not exposed to user. */
    public const TTL_SECONDS = 300; // 5 minutes

    public const MAX_ATTEMPTS = 5;
    public const CODE_LENGTH = 6;

    /** After this many force-resend attempts while a code is still trusted → short lock. */
    public const MAX_FORCE_RESENDS = 2;

    /** Lock duration after excessive force-resend (seconds). */
    public const ABUSE_LOCK_SECONDS = 120; // 2 minutes

    public function __construct(
        private readonly SmsSenderInterface $smsSender,
        private readonly AuthenticationService $authenticationService,
    ) {
    }

    /**
     * Request OTP for an existing active user mobile.
     *
     * While an unconsumed non-expired OTP exists, a normal request does not send a new SMS;
     * the user is told the last code is still valid (no duration disclosed).
     * forceResend=true may issue a new code, subject to abuse lock after MAX_FORCE_RESENDS.
     *
     * @return array{expires_in:int, resend_available_in:int, code_still_valid?:bool, debug_code?:string}
     */
    public function requestOtp(string $mobile, ?string $requestIp = null, bool $forceResend = false): array
    {
        $mobile = $this->normalizeMobile($mobile);

        $user = User::query()
            ->where('mobile', $mobile)
            ->whereNull('deleted_at')
            ->first();

        // Generic message to reduce user enumeration
        if (!$user || (int) $user->status !== 1) {
            throw new HttpException(422, 'در صورت صحت شماره، کد تأیید ارسال می‌شود.');
        }

        $this->assertNotAbuseLocked($mobile);

        $active = IdentityLoginOtp::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('last_sent_at')
            ->first();

        if ($active && !$forceResend) {
            $remaining = max(0, $active->expires_at->getTimestamp() - now()->getTimestamp());

            // Soft message — do not reveal how long the code remains valid
            throw new HttpException(
                429,
                'آخرین کدی که دریافت کردید هنوز معتبر است. همان را وارد کنید.'
            );
        }

        if ($active && $forceResend) {
            $forceCount = (int) Cache::get($this->forceResendCacheKey($mobile), 0);
            if ($forceCount >= self::MAX_FORCE_RESENDS) {
                Cache::put(
                    $this->abuseLockCacheKey($mobile),
                    true,
                    self::ABUSE_LOCK_SECONDS
                );
                Cache::forget($this->forceResendCacheKey($mobile));
                throw new HttpException(
                    429,
                    'به‌دلیل درخواست‌های مکرر، فعلاً امکان ارسال کد جدید وجود ندارد. کمی بعد دوباره تلاش کنید.'
                );
            }

            // Invalidate previous active OTP before issuing a new one
            $active->consumed_at = now();
            $active->save();

            Cache::put(
                $this->forceResendCacheKey($mobile),
                $forceCount + 1,
                self::TTL_SECONDS
            );
        }

        $plainCode = $this->generateCode();

        IdentityLoginOtp::create([
            'otp_id'        => (string) Str::uuid(),
            'mobile'        => $mobile,
            'code_hash'     => Hash::make($plainCode),
            'expires_at'    => now()->addSeconds(self::TTL_SECONDS),
            'last_sent_at'  => now(),
            'consumed_at'   => null,
            'attempt_count' => 0,
            'request_ip'    => $requestIp,
        ]);

        $this->smsSender->send(
            $mobile,
            'کد ورود هماره ERP: '.$plainCode
        );

        $payload = [
            'expires_in'          => self::TTL_SECONDS,
            'resend_available_in' => self::TTL_SECONDS,
        ];

        // Local/dev aid only — never enable in production
        if (config('app.debug') === true) {
            $payload['debug_code'] = $plainCode;
        }

        return $payload;
    }

    /**
     * Verify OTP and complete login (same session shape as password login).
     */
    public function verifyOtp(string $mobile, string $code, ?string $tenantId = null): array
    {
        $mobile = $this->normalizeMobile($mobile);
        $code = trim($code);

        $otp = IdentityLoginOtp::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->orderByDesc('last_sent_at')
            ->first();

        if (!$otp || $otp->isExpired()) {
            throw new HttpException(401, 'کد منقضی شده یا یافت نشد. دوباره درخواست کنید.');
        }

        if ((int) $otp->attempt_count >= self::MAX_ATTEMPTS) {
            $otp->consumed_at = now();
            $otp->save();
            throw new HttpException(429, 'تعداد تلاش بیش از حد مجاز است. دوباره درخواست کد دهید.');
        }

        if (!Hash::check($code, $otp->code_hash)) {
            $otp->attempt_count = (int) $otp->attempt_count + 1;
            $otp->save();
            throw new HttpException(401, 'کد وارد شده نادرست است.');
        }

        $otp->consumed_at = now();
        $otp->save();

        // Successful verify clears force-resend / abuse state for this mobile
        Cache::forget($this->forceResendCacheKey($mobile));
        Cache::forget($this->abuseLockCacheKey($mobile));

        $user = User::query()
            ->where('mobile', $mobile)
            ->whereNull('deleted_at')
            ->first();

        if (!$user || (int) $user->status !== 1) {
            throw new HttpException(401, 'امکان ورود با این شماره وجود ندارد.');
        }

        return $this->authenticationService->completeLoginForUser($user, $tenantId);
    }

    private function assertNotAbuseLocked(string $mobile): void
    {
        if (Cache::has($this->abuseLockCacheKey($mobile))) {
            throw new HttpException(
                429,
                'به‌دلیل درخواست‌های مکرر، فعلاً امکان ارسال کد جدید وجود ندارد. کمی بعد دوباره تلاش کنید.'
            );
        }
    }

    private function forceResendCacheKey(string $mobile): string
    {
        return 'identity.otp.force_resend:'.$mobile;
    }

    private function abuseLockCacheKey(string $mobile): string
    {
        return 'identity.otp.abuse_lock:'.$mobile;
    }

    private function generateCode(): string
    {
        $max = (10 ** self::CODE_LENGTH) - 1;
        $num = random_int(0, $max);

        return str_pad((string) $num, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        return $digits;
    }
}
