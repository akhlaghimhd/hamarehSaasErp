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

    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

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

        if (! $user) {
            $this->recordAttempt($username, $ipAddress, $userAgent, false, 'user_not_found');
            $this->auditAuthFailure($username, $ipAddress, $userAgent, 'user_not_found');
            throw new InvalidArgumentException('Invalid credentials.');
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            $this->recordAttempt($username, $ipAddress, $userAgent, false, 'account_locked');
            $this->auditAuthFailure($username, $ipAddress, $userAgent, 'account_locked', $user->admin_user_id);
            throw new InvalidArgumentException('Account is temporarily locked.');
        }

        if ($user->status !== 1) {
            $this->recordAttempt($username, $ipAddress, $userAgent, false, 'inactive');
            $this->auditAuthFailure($username, $ipAddress, $userAgent, 'inactive', $user->admin_user_id);
            throw new InvalidArgumentException('Account is inactive.');
        }

        if (! Hash::check($password, $user->password_hash)) {
            $failed = ((int) $user->failed_login_count) + 1;
            $user->failed_login_count = $failed;
            if ($failed >= self::MAX_FAILED) {
                $user->locked_until = now()->addMinutes(self::LOCK_MINUTES);
                $user->failed_login_count = 0;
            }
            $user->save();

            $this->recordAttempt($username, $ipAddress, $userAgent, false, 'bad_password');
            $this->auditAuthFailure($username, $ipAddress, $userAgent, 'bad_password', $user->admin_user_id);
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

            $this->auditLogService->write(
                entityName: 'admin_auth',
                actionType: 'LOGIN',
                entityId: $user->admin_user_id,
                adminUserId: $user->admin_user_id,
                details: [
                    'username'   => $username,
                    'session_id' => $session->session_id,
                ],
                severity: 1,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                sessionId: $session->session_id,
                createdBy: $user->admin_user_id
            );

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
        $session = AdminUserSession::query()
            ->where('token_hash', $hash)
            ->where('is_active', true)
            ->first();

        if ($session) {
            $session->is_active = false;
            $session->save();

            $this->auditLogService->write(
                entityName: 'admin_auth',
                actionType: 'LOGOUT',
                entityId: $session->admin_user_id,
                adminUserId: $session->admin_user_id,
                details: ['session_id' => $session->session_id],
                severity: 1,
                sessionId: $session->session_id,
                createdBy: $session->admin_user_id
            );
        }
    }

    private function auditAuthFailure(
        string $username,
        string $ipAddress,
        ?string $userAgent,
        string $reason,
        ?string $adminUserId = null
    ): void {
        $this->auditLogService->write(
            entityName: 'admin_auth',
            actionType: 'LOGIN_FAILED',
            entityId: $adminUserId,
            adminUserId: $adminUserId,
            details: [
                'username' => $username,
                'reason'   => $reason,
            ],
            severity: 2,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdBy: $adminUserId
        );
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
