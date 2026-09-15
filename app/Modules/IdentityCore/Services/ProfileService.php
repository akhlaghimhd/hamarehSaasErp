<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\UpsertUserProfileDTO;
use App\Modules\IdentityCore\DTOs\SelfUpsertUserProfileDTO;
use App\Modules\IdentityCore\Models\UserProfile;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class ProfileService
{
    /**
     * Get profile for a user that is a member of the current tenant.
     * Profile itself is platform-level (no tenant_id); isolation is via tenant membership.
     */
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

    /**
     * Admin upsert — full identity fields (national_id, gender 1|2, birth_date, …).
     */
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

    /**
     * Self-service upsert: only display_bio + address change request (pending approval).
     */
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

            if ($dto->hasAddress) {
                $newAddress = $dto->address;
                $current = $profile->address;
                if ($newAddress !== $current) {
                    $payload['pending_address'] = $newAddress;
                    $payload['address_change_status'] = UserProfile::ADDRESS_STATUS_PENDING;
                }
            }

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

    /**
     * Store a single avatar image for the current user (replaces previous).
     */
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
            $filename = 'avatar.' . $ext;

            // Remove previous files in user avatar dir
            Storage::disk('public')->deleteDirectory($dir);
            $path = $file->storeAs($dir, $filename, 'public');
            $publicUrl = Storage::disk('public')->url($path);

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
     * Admin approves pending address → copies pending_address into address.
     */
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

            $this->logEventOutbox(
                $tenantId,
                'user_profiles',
                $profile->profile_id,
                'identity.user_profile.address_approved.v1',
                [
                    'profile_id' => $profile->profile_id,
                    'user_id'    => $userId,
                    'address'    => $profile->address,
                ]
            );

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
