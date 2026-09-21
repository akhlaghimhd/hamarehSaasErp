<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Contracts\SmsSenderInterface;
use App\Modules\IdentityCore\Models\IdentityLoginOtp;
use App\Modules\IdentityCore\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OtpLoginService
{
    /**
     * How long each issued OTP remains valid for login (multi-use until expiry or new code).
     */
    public const TTL_SECONDS = 600; // 10 minutes

    /**
     * Hard minimum gap between issuing two NEW codes for the same mobile.
     */
    public const RESEND_COOLDOWN_SECONDS = 600; // 10 minutes

    public const MAX_ATTEMPTS = 5;

    public const CODE_LENGTH = 6;

    public function __construct(
        private readonly SmsSenderInterface $smsSender,
        private readonly AuthenticationService $authenticationService,
    ) {
    }

    /**
     * Request OTP for an existing active user mobile.
     *
     * - Active valid code + no force → do not send; tell user to use existing code.
     * - Force / no active but last send < 10 min → block with 10-minute message.
     * - Otherwise issue NEW code and invalidate all previous codes for this mobile.
     *
     * @return array{expires_in:int, code_still_valid?:bool, resend_available_in:int, debug_code?:string}
     */
    public function requestOtp(string $mobile, ?string $requestIp = null, bool $forceResend = false): array
    {
        $mobile = $this->normalizeMobile($mobile);

        $user = User::query()
            ->where('mobile', $mobile)
            ->whereNull('deleted_at')
            ->first();

        if (!$user || (int) $user->status !== 1) {
            throw new HttpException(422, 'در صورت صحت شماره، کد تأیید ارسال می‌شود.');
        }

        $hasActiveCode = IdentityLoginOtp::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->exists();

        $latestAny = IdentityLoginOtp::query()
            ->where('mobile', $mobile)
            ->orderByDesc('last_sent_at')
            ->first();

        $secondsSinceLastSend = null;
        if ($latestAny && $latestAny->last_sent_at) {
            $secondsSinceLastSend = max(
                0,
                (int) (now()->getTimestamp() - $latestAny->last_sent_at->getTimestamp())
            );
        }

        $withinCooldown = $secondsSinceLastSend !== null
            && $secondsSinceLastSend < self::RESEND_COOLDOWN_SECONDS;

        // Case A: valid code exists and user is not asking for a new one
        if ($hasActiveCode && !$forceResend) {
            throw new HttpException(
                429,
                'آخرین کدی که دریافت کردید هنوز معتبر است. همان را وارد کنید.'
            );
        }

        // Case B: wants a new code but still inside the 10-minute window
        if ($withinCooldown) {
            throw new HttpException(
                429,
                'رمز موقت طی ۱۰ دقیقه گذشته برای شما ارسال شده است.'
            );
        }

        // Case C: issue a new code — invalidate ALL previous codes for this mobile
        IdentityLoginOtp::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

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
            'resend_available_in' => self::RESEND_COOLDOWN_SECONDS,
            'code_still_valid'    => false,
        ];

        if (config('app.debug') === true) {
            $payload['debug_code'] = $plainCode;
        }

        return $payload;
    }

    /**
     * Verify OTP and complete login (same session shape as password login).
     * Successful verify does NOT burn the code — multi-use until expiry or new code request.
     */
    public function verifyOtp(string $mobile, string $code, ?string $tenantId = null): array
    {
        $user = $this->assertValidOtp($mobile, $code);

        return $this->authenticationService->completeLoginForUser($user, $tenantId);
    }

    /**
     * Verify OTP and return the User (no session). Used by forgot-password confirm.
     * Does not burn the code (same multi-use policy).
     */
    public function verifyOtpForPasswordReset(string $mobile, string $code): User
    {
        return $this->assertValidOtp($mobile, $code);
    }

    /**
     * Accept any unconsumed, non-expired OTP whose hash matches.
     * Does NOT set consumed_at on success — code remains reusable until TTL or a new code is issued.
     */
    private function assertValidOtp(string $mobile, string $code): User
    {
        $mobile = $this->normalizeMobile($mobile);
        $code = trim($code);

        $candidates = IdentityLoginOtp::query()
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('last_sent_at')
            ->get();

        if ($candidates->isEmpty()) {
            $anyRecent = IdentityLoginOtp::query()
                ->where('mobile', $mobile)
                ->orderByDesc('last_sent_at')
                ->first();

            if ($anyRecent && $anyRecent->isExpired() && $anyRecent->consumed_at === null) {
                $anyRecent->consumed_at = now();
                $anyRecent->save();
                throw new HttpException(
                    401,
                    'این کد منقضی شده است. برای دریافت کد جدید روی «ارسال مجدد» بزنید.'
                );
            }

            throw new HttpException(
                401,
                'این کد دیگر قابل استفاده نیست. برای دریافت کد جدید روی «ارسال مجدد» بزنید.'
            );
        }

        $matched = null;
        foreach ($candidates as $otp) {
            if ((int) $otp->attempt_count >= self::MAX_ATTEMPTS) {
                continue;
            }
            if (Hash::check($code, $otp->code_hash)) {
                $matched = $otp;
                break;
            }
        }

        if ($matched === null) {
            $latest = $candidates->first();
            $latest->attempt_count = (int) $latest->attempt_count + 1;
            $latest->save();

            if ((int) $latest->attempt_count >= self::MAX_ATTEMPTS) {
                $latest->consumed_at = now();
                $latest->save();
                throw new HttpException(429, 'تعداد تلاش بیش از حد مجاز است. دوباره درخواست کد دهید.');
            }

            throw new HttpException(401, 'کد وارد شده نادرست است.');
        }

        // Multi-use: do not set consumed_at on successful match

        $user = User::query()
            ->where('mobile', $mobile)
            ->whereNull('deleted_at')
            ->first();

        if (!$user || (int) $user->status !== 1) {
            throw new HttpException(401, 'امکان ورود با این شماره وجود ندارد.');
        }

        return $user;
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
