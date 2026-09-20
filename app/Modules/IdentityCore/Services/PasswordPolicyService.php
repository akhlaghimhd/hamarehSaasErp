<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Password policy for IdentityCore.
 *
 * Rules (product):
 * - minimum 6 characters
 * - at least one uppercase English letter (A-Z)
 * - at least one lowercase English letter (a-z)
 * - at least one digit OR symbol (non-letter)
 * - only printable ASCII (no Persian / non-Latin scripts)
 * - must not contain obvious personal data (first/last name, mobile digits, email local-part)
 */
class PasswordPolicyService
{
    public const MIN_LENGTH = 6;

    /**
     * Validate password against policy. Throws HttpException 422 with Persian message on failure.
     */
    public function assertValid(string $password, ?User $user = null): void
    {
        $password = (string) $password;

        if (mb_strlen($password) < self::MIN_LENGTH) {
            throw new HttpException(422, 'رمز عبور باید حداقل ۶ کاراکتر باشد.');
        }

        // Reject non-ASCII / Persian / Arabic script characters
        if (preg_match('/[^\x20-\x7E]/', $password)) {
            throw new HttpException(
                422,
                'رمز عبور فقط می‌تواند شامل حروف انگلیسی، اعداد و نمادها باشد. استفاده از حروف فارسی مجاز نیست.'
            );
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new HttpException(422, 'رمز عبور باید حداقل یک حرف بزرگ انگلیسی (A-Z) داشته باشد.');
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new HttpException(422, 'رمز عبور باید حداقل یک حرف کوچک انگلیسی (a-z) داشته باشد.');
        }

        // At least one digit or symbol
        if (!preg_match('/[0-9]/', $password) && !preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new HttpException(
                422,
                'رمز عبور باید حداقل یک عدد یا نماد (مثل ! @ # $) داشته باشد.'
            );
        }

        if ($user !== null) {
            $this->assertNotPersonalData($password, $user);
        }
    }

    private function assertNotPersonalData(string $password, User $user): void
    {
        $lower = mb_strtolower($password);

        $candidates = [];

        if (filled($user->first_name)) {
            $candidates[] = mb_strtolower(preg_replace('/\s+/', '', (string) $user->first_name) ?? '');
        }
        if (filled($user->last_name)) {
            $candidates[] = mb_strtolower(preg_replace('/\s+/', '', (string) $user->last_name) ?? '');
        }
        if (filled($user->first_name) && filled($user->last_name)) {
            $fn = mb_strtolower(preg_replace('/\s+/', '', (string) $user->first_name) ?? '');
            $ln = mb_strtolower(preg_replace('/\s+/', '', (string) $user->last_name) ?? '');
            if ($fn !== '' && $ln !== '') {
                $candidates[] = $fn.$ln;
                $candidates[] = $ln.$fn;
            }
        }

        if (filled($user->mobile)) {
            $digits = preg_replace('/\D+/', '', (string) $user->mobile) ?? '';
            if (strlen($digits) >= 6) {
                $candidates[] = $digits;
                // last 6–10 digits common patterns
                $candidates[] = substr($digits, -10);
                $candidates[] = substr($digits, -8);
                $candidates[] = substr($digits, -6);
            }
        }

        if (filled($user->email) && str_contains((string) $user->email, '@')) {
            $local = mb_strtolower(explode('@', (string) $user->email)[0] ?? '');
            $local = preg_replace('/[^a-z0-9]/', '', $local) ?? '';
            if (strlen($local) >= 3) {
                $candidates[] = $local;
            }
        }

        foreach (array_unique(array_filter($candidates)) as $piece) {
            if ($piece === '' || mb_strlen($piece) < 3) {
                continue;
            }
            if (str_contains($lower, $piece)) {
                throw new HttpException(
                    422,
                    'رمز عبور نباید شامل نام، نام‌خانوادگی، شماره موبایل یا بخش محلی ایمیل شما باشد.'
                );
            }
        }
    }
}
