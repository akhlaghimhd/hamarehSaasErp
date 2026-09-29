<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W1-03 — TOTP MFA foundation (RFC 6238) without external packages.
 */
class MfaService
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    public const WINDOW = 1; // ±1 step clock skew
    public const RECOVERY_COUNT = 8;

    /**
     * Start enrollment: generate secret, store encrypted, return otpauth URI + plaintext secret for QR.
     * Does NOT enable two_factor until confirmEnable succeeds.
     */
    public function beginEnable(User $user): array
    {
        $credential = $this->requireCredential($user);

        if ($credential->two_factor_enabled && $credential->two_factor_confirmed_at) {
            throw new HttpException(422, 'احراز هویت دو مرحله‌ای از قبل فعال است.');
        }

        $secret = $this->generateBase32Secret(20);
        $credential->totp_secret = Crypt::encryptString($secret);
        $credential->two_factor_enabled = false;
        $credential->two_factor_confirmed_at = null;
        $credential->recovery_codes = null;
        $credential->row_version = ((int) ($credential->row_version ?? 1)) + 1;
        $credential->save();

        $label = rawurlencode($user->email ?: $user->mobile ?: $user->user_id);
        $issuer = rawurlencode(config('app.name', 'HamarehERP'));

        return [
            'secret'      => $secret,
            'otpauth_uri' => "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&period=" . self::PERIOD . '&digits=' . self::DIGITS,
            'period'      => self::PERIOD,
            'digits'      => self::DIGITS,
        ];
    }

    /**
     * Confirm enrollment with a valid TOTP code; enables MFA and returns recovery codes (plaintext once).
     */
    public function confirmEnable(User $user, string $code): array
    {
        $credential = $this->requireCredential($user);
        $secret = $this->decryptSecret($credential);

        if (!$this->verifyTotp($secret, $code)) {
            throw new HttpException(422, 'کد تأیید نامعتبر است.');
        }

        $plainRecovery = $this->generateRecoveryCodes(self::RECOVERY_COUNT);
        $hashed = array_map(fn (string $c) => Hash::make($c), $plainRecovery);

        $credential->two_factor_enabled = true;
        $credential->two_factor_confirmed_at = now();
        $credential->recovery_codes = $hashed;
        $credential->row_version = ((int) ($credential->row_version ?? 1)) + 1;
        $credential->save();

        return [
            'enabled'        => true,
            'recovery_codes' => $plainRecovery,
        ];
    }

    public function disable(User $user, string $codeOrRecovery): void
    {
        $credential = $this->requireCredential($user);

        if (!$credential->two_factor_enabled) {
            throw new HttpException(422, 'احراز هویت دو مرحله‌ای فعال نیست.');
        }

        if (!$this->verifyAny($credential, $codeOrRecovery)) {
            throw new HttpException(422, 'کد نامعتبر است.');
        }

        $credential->two_factor_enabled = false;
        $credential->two_factor_confirmed_at = null;
        $credential->totp_secret = null;
        $credential->recovery_codes = null;
        $credential->row_version = ((int) ($credential->row_version ?? 1)) + 1;
        $credential->save();
    }

    public function isRequired(User $user): bool
    {
        $c = $user->credential;

        return $c
            && (bool) $c->two_factor_enabled
            && $c->two_factor_confirmed_at !== null
            && !empty($c->totp_secret);
    }

    public function verifyAny(UserCredential $credential, string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        try {
            $secret = $this->decryptSecret($credential);
            if ($this->verifyTotp($secret, $code)) {
                return true;
            }
        } catch (\Throwable) {
            // fall through to recovery
        }

        return $this->consumeRecoveryCode($credential, $code);
    }

    public function verifyTotp(string $base32Secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, self::PERIOD);

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals($this->hotp($base32Secret, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private function consumeRecoveryCode(UserCredential $credential, string $plain): bool
    {
        $codes = $credential->recovery_codes;
        if (!is_array($codes) || $codes === []) {
            return false;
        }

        $matched = null;
        foreach ($codes as $idx => $hash) {
            if (is_string($hash) && Hash::check($plain, $hash)) {
                $matched = $idx;
                break;
            }
        }

        if ($matched === null) {
            return false;
        }

        unset($codes[$matched]);
        $credential->recovery_codes = array_values($codes);
        $credential->row_version = ((int) ($credential->row_version ?? 1)) + 1;
        $credential->save();

        return true;
    }

    private function requireCredential(User $user): UserCredential
    {
        $credential = $user->credential;
        if (!$credential) {
            throw new HttpException(422, 'اطلاعات احراز هویت کاربر یافت نشد.');
        }

        return $credential;
    }

    private function decryptSecret(UserCredential $credential): string
    {
        if (empty($credential->totp_secret)) {
            throw new HttpException(422, 'رمز TOTP تنظیم نشده است.');
        }

        return Crypt::decryptString($credential->totp_secret);
    }

    private function generateBase32Secret(int $bytes = 20): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $raw = random_bytes($bytes);
        $bits = '';
        foreach (str_split($raw) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $secret .= $alphabet[bindec($chunk)];
        }

        return $secret;
    }

    private function hotp(string $base32Secret, int $counter): string
    {
        $key = $this->base32Decode($base32Secret);
        $binCounter = pack('N*', 0, $counter); // 8-byte big-endian
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $truncated = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        );

        $otp = $truncated % (10 ** self::DIGITS);

        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';
        foreach (str_split($secret) as $char) {
            $val = strpos($alphabet, $char);
            if ($val === false) {
                continue;
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $data = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $data .= chr(bindec($byte));
            }
        }

        return $data;
    }

    /** @return list<string> */
    private function generateRecoveryCodes(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }

        return $codes;
    }
}
