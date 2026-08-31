<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\UpsertUserProfileDTO;
use App\Modules\IdentityCore\Models\UserProfile;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Facades\DB;
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
     * Create or update profile for a tenant-member user.
     */
    public function upsert(UpsertUserProfileDTO $dto): UserProfile
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
     * Soft-delete profile (Law 1.4).
     */
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
