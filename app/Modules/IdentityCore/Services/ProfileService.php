<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\UpsertUserProfileDTO;
use App\Modules\IdentityCore\DTOs\SelfUpsertUserProfileDTO;
use App\Modules\IdentityCore\Models\UserProfile;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\IdentityLoginOtp;
use App\Modules\IdentityCore\Contracts\SmsSenderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class ProfileService
{
    public function __construct(
        private readonly SmsSenderInterface $smsSender,
    ) {
    }

    public function getByUserId(string $userId): UserProfile
    {
        $tenantId = $this->getTenantId();
        $this->assertUserBelongsToTenant($userId, $tenantId);

        $profile = UserProfile::query()
            ->where('user_id', $userId)
            ->first();

        if (!$profile) {
            throw (new ModelNotFoundException())->setModel(UserProfile::class, [$userId]);
        }

        return $profile;
    }

    public function upsert(UpsertUserProfileDTO $dto): UserProfile
    {
        $tenantId = $this->getTenantId();
        $this->assertUserBelongsToTenant($dto->userId, $tenantId);

        if (!User::where('user_id', $dto->userId)->exists()) {
            throw (new ModelNotFoundException())->setModel(User::class, [$dto->userId]);
        }

        if ($dto->gender !== null && !in_array((int) $dto->gender, [1, 2], true)) {
            throw new Exception('Gender must be 1 (male) or 2 (female).');
        }

        return DB::transaction(function () use ($dto, $tenantId) {
            $profile = UserProfile::query()
                ->where('user_id', $dto->userId)
                ->first();

            $payload = array_filter([
                'national_id' => $dto->nationalId,
                'birth_date'  => $dto->birthDate,
                'avatar_url'  => $dto->avatarUrl,
                'gender'      => $dto->gender,
                'address'     => $dto->address,
                'phone'       => $dto->phone,
                'description' => $dto->description,
            ], fn ($value) => !is_null($value));

            if ($profile) {
                $payload['row_version'] = ((int) ($profile->row_version ?? 1)) + 1;
                $profile->update($payload);
                $eventType = 'identity.user_profile.updated.v1';
            } else {
                $profile = UserProfile::create(array_merge($payload, [
                    'user_id'     => $dto->userId,
                    'row_version' => 1,
                ]));
                $eventType = 'identity.user_profile.created.v1';
            }

            $this->logEventOutbox(
                $tenantId,
                'user_profiles',
                $profile->profile_id,
                $eventType,
                [
                    'profile_id' => $profile->profile_id,
                    'user_id'    => $dto->userId,
                    'changes'    => $payload,
                ]
            );

            return $profile->fresh();
        });
    }

    public function upsertSelf(SelfUpsertUserProfileDTO $dto): UserProfile
    {
        $tenantId = $this->getTenantId();
        $this->assertUserBelongsToTenant($dto->userId, $tenantId);

        if (!User::where('user_id', $dto->userId)->exists()) {
            throw (new ModelNotFoundException())->setModel(User::class, [$dto->userId]);
        }

        return DB::transaction(function () use ($dto, $tenantId) {
            $profile = UserProfile::query()
                ->where('user_id', $dto->userId)
                ->first();

            if (!$profile) {
                $profile = UserProfile::create([
                    'user_id'     => $dto->userId,
                    'row_version' => 1,
                ]);
            }

            $payload = [];

            if ($dto->hasDisplayBio) {
                $payload['description'] = $dto->displayBio;
            }

            // Address change is no longer self-service (policy 2026-09-15).
            // Keep DTO field ignored intentionally.

            if ($payload !== []) {
                $payload['row_version'] = ((int) ($profile->row_version ?? 1)) + 1;
                $profile->update($payload);

                $this->logEventOutbox(
                    $tenantId,
                    'user_profiles',
                    $profile->profile_id,
                    'identity.user_profile.self_updated.v1',
                    [
                        'profile_id' => $profile->profile_id,
                        'user_id'    => $dto->userId,
                        'changes'    => $payload,
                    ]
                );
            }

            return $profile->fresh();
        });
    }

    public function uploadAvatar(string $userId, UploadedFile $file): UserProfile
    {
        $tenantId = $this->getTenantId();
        $this->assertUserBelongsToTenant($userId, $tenantId);

        return DB::transaction(function () use ($userId, $file, $tenantId) {
            $profile = UserProfile::query()
                ->where('user_id', $userId)
                ->first();

            if (!$profile) {
                $profile = UserProfile::create([
                    'user_id'     => $userId,
                    'row_version' => 1,
                ]);
            }

            $dir = 'avatars/' . $userId;
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $ext = 'jpg';
            }
            $filename = 'avatar_' . time() . '.' . $ext;

            Storage::disk('public')->deleteDirectory($dir);
            $path = $file->storeAs($dir, $filename, 'public');

            // Absolute URL so SPA on another origin can load the image
            $publicUrl = $this->publicUrlForPath($path);

            $profile->update([
                'avatar_url'  => $publicUrl,
                'row_version' => ((int) ($profile->row_version ?? 1)) + 1,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'user_profiles',
                $profile->profile_id,
                'identity.user_profile.avatar_updated.v1',
                [
                    'profile_id' => $profile->profile_id,
                    'user_id'    => $userId,
                    'avatar_url' => $publicUrl,
                ]
            );

            return $profile->fresh();
        });
    }

    /**
     * Step 1: send OTP to the *new* mobile for authenticated user.
     */
    public function requestMobileChange(string $userId, string $newMobile, ?string $requestIp = null): array
    {
        $tenantId = $this->getTenantId();
        $this->assertUserBelongsToTenant($userId, $tenantId);

        $newMobile = $this->normalizeMobile($newMobile);

        if (!preg_match('/^09\d{9}$/', $newMobile)) {
            throw new HttpException(422, 'شماره موبایل معتبر نیست.');
        }

        $user = User::query()->where('user_id', $userId)->whereNull('deleted_at')->first();
        if (!$user) {
            throw (new ModelNotFoundException())->setModel(User::class, [$userId]);
        }

        if ($user->mobile === $newMobile) {
            throw new HttpException(422, 'شماره جدید با شماره فعلی یکسان است.');
        }

        $taken = User::query()
            ->where('mobile', $newMobile)
            ->where('user_id', '!=', $userId)
            ->whereNull('deleted_at')
            ->exists();

        if ($taken) {
            throw new HttpException(422, 'این شماره قبلاً ثبت شده است.');
        }

        $active = IdentityLoginOtp::query()
            ->where('mobile', $newMobile)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('last_sent_at')
            ->first();

        if ($active) {
            throw new HttpException(429, 'کد قبلی هنوز معتبر است.');
        }

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        IdentityLoginOtp::create([
            'otp_id'        => (string) Str::uuid(),
            'mobile'        => $newMobile,
            'code_hash'     => Hash::make($plainCode),
            'expires_at'    => now()->addSeconds(180),
            'last_sent_at'  => now(),
            'consumed_at'   => null,
            'attempt_count' => 0,
            'request_ip'    => $requestIp,
        ]);

        $this->smsSender->send(
            $newMobile,
            'کد تأیید تغییر موبایل هماره ERP: '.$plainCode
        );

        $payload = [
            'expires_in'          => 180,
            'resend_available_in' => 180,
        ];

        if (config('app.debug') === true) {
            $payload['debug_code'] = $plainCode;
        }

        return $payload;
    }

    /**
     * Step 2: verify OTP on new mobile and update users.mobile.
     */
    public function verifyMobileChange(string $userId, string $newMobile, string $code): User
    {
        $tenantId = $this->getTenantId();
        $this->assertUserBelongsToTenant($userId, $tenantId);

        $newMobile = $this->normalizeMobile($newMobile);
        $code = trim($code);

        $otp = IdentityLoginOtp::query()
            ->where('mobile', $newMobile)
            ->whereNull('consumed_at')
            ->orderByDesc('last_sent_at')
            ->first();

        if (!$otp || $otp->isExpired()) {
            throw new HttpException(401, 'کد منقضی شده یا یافت نشد.');
        }

        if ((int) $otp->attempt_count >= 5) {
            $otp->consumed_at = now();
            $otp->save();
            throw new HttpException(429, 'تعداد تلاش بیش از حد مجاز است.');
        }

        if (!Hash::check($code, $otp->code_hash)) {
            $otp->attempt_count = (int) $otp->attempt_count + 1;
            $otp->save();
            throw new HttpException(401, 'کد وارد شده نادرست است.');
        }

        $otp->consumed_at = now();
        $otp->save();

        $taken = User::query()
            ->where('mobile', $newMobile)
            ->where('user_id', '!=', $userId)
            ->whereNull('deleted_at')
            ->exists();

        if ($taken) {
            throw new HttpException(422, 'این شماره قبلاً ثبت شده است.');
        }

        $user = User::query()->where('user_id', $userId)->firstOrFail();
        $user->mobile = $newMobile;
        $user->row_version = ((int) ($user->row_version ?? 1)) + 1;
        $user->save();

        $this->logEventOutbox(
            $tenantId,
            'users',
            $userId,
            'identity.user.mobile_changed.v1',
            ['user_id' => $userId, 'mobile' => $newMobile]
        );

        return $user->fresh();
    }

    public function approveAddressChange(string $userId): UserProfile
    {
        $tenantId = $this->getTenantId();
        $this->assertUserBelongsToTenant($userId, $tenantId);

        return DB::transaction(function () use ($userId, $tenantId) {
            $profile = UserProfile::query()
                ->where('user_id', $userId)
                ->firstOrFail();

            if ((int) $profile->address_change_status !== UserProfile::ADDRESS_STATUS_PENDING) {
                throw new Exception('No pending address change to approve.');
            }

            $profile->update([
                'address'               => $profile->pending_address,
                'pending_address'       => null,
                'address_change_status' => UserProfile::ADDRESS_STATUS_APPROVED,
                'row_version'           => ((int) ($profile->row_version ?? 1)) + 1,
            ]);

            return $profile->fresh();
        });
    }

    public function softDelete(string $userId): void
    {
        $tenantId = $this->getTenantId();
        $this->assertUserBelongsToTenant($userId, $tenantId);

        DB::transaction(function () use ($userId, $tenantId) {
            $profile = UserProfile::query()
                ->where('user_id', $userId)
                ->firstOrFail();

            $profile->delete();

            $this->logEventOutbox(
                $tenantId,
                'user_profiles',
                $profile->profile_id,
                'identity.user_profile.deleted.v1',
                [
                    'profile_id' => $profile->profile_id,
                    'user_id'    => $userId,
                ]
            );
        });
    }

    private function publicUrlForPath(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return $base.'/storage/'.$path;
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

    private function assertUserBelongsToTenant(string $userId, string $tenantId): void
    {
        $membership = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->first();

        if (!$membership) {
            throw (new ModelNotFoundException())->setModel(TenantUser::class, [$userId]);
        }
    }

    private function getTenantId(): string
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        if (!$tenantId) {
            throw new Exception('Tenant Context is missing. Architecture Violation.');
        }

        return $tenantId;
    }

    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
