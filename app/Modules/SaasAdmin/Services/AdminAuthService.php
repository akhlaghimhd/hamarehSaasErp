<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\AdminLoginAttempt;
use App\Modules\SaasAdmin\Models\AdminUser;
use App\Modules\SaasAdmin\Models\AdminUserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdminAuthService
{
    private const MAX_FAILED = 5;
    private const LOCK_MINUTES = 15;
    private const SESSION_HOURS = 8;

    /**
     * @return array{admin_user: AdminUser, session: AdminUserSession, token: string}
     */
    public function login(
        string $username,
        string $password,
        string $ipAddress,
        ?string $userAgent = null
    ): array {
        // Failure paths must NOT run inside a rolled-back transaction,
        // otherwise admin_login_attempts and failed_login_count would be lost.

        $user = AdminUser::query()
            ->where('username', $username)
            ->whereNull('deleted_at')
            ->first();

        if (!$user) {
            $this->recordAttempt($username, $ipAddress, $userAgent, false, 'user_not_found');
            throw new InvalidArgumentException('Invalid credentials.');
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            $this->recordAttempt($username, $ipAddress, $userAgent, false, 'account_locked');
            throw new InvalidArgumentException('Account is temporarily locked.');
        }

        if ($user->status !== 1) {
            $this->recordAttempt($username, $ipAddress, $userAgent, false, 'inactive');
            throw new InvalidArgumentException('Account is inactive.');
        }

        if (!Hash::check($password, $user->password_hash)) {
            $failed = ((int) $user->failed_login_count) + 1;
            $user->failed_login_count = $failed;
            if ($failed >= self::MAX_FAILED) {
                $user->locked_until = now()->addMinutes(self::LOCK_MINUTES);
                $user->failed_login_count = 0;
            }
            $user->save();

            $this->recordAttempt($username, $ipAddress, $userAgent, false, 'bad_password');
            throw new InvalidArgumentException('Invalid credentials.');
        }

        return DB::transaction(function () use ($user, $username, $ipAddress, $userAgent) {
            $user->failed_login_count = 0;
            $user->locked_until = null;
            $user->last_login_at = now();
            $user->save();

            $this->recordAttempt($username, $ipAddress, $userAgent, true, null);

            $plainToken = Str::random(64);
            $session = AdminUserSession::create([
                'session_id'       => (string) Str::uuid(),
                'admin_user_id'    => $user->admin_user_id,
                'token_hash'       => hash('sha256', $plainToken),
                'ip_address'       => $ipAddress,
                'user_agent'       => $userAgent,
                'is_active'        => true,
                'created_at'       => now(),
                'expires_at'       => now()->addHours(self::SESSION_HOURS),
                'last_activity_at' => now(),
            ]);

            return [
                'admin_user' => $user->fresh(),
                'session'    => $session,
                'token'      => $plainToken,
            ];
        });
    }

    public function logout(string $token): void
    {
        $hash = hash('sha256', $token);
        AdminUserSession::query()
            ->where('token_hash', $hash)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function recordAttempt(
        string $username,
        string $ipAddress,
        ?string $userAgent,
        bool $successful,
        ?string $failureReason
    ): void {
        AdminLoginAttempt::create([
            'attempt_id'     => (string) Str::uuid(),
            'username'       => $username,
            'ip_address'     => $ipAddress,
            'user_agent'     => $userAgent,
            'is_successful'  => $successful,
            'failure_reason' => $failureReason,
            'attempted_at'   => now(),
        ]);
    }
}
