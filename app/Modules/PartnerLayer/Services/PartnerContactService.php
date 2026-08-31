<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerContact;
use App\Modules\PartnerLayer\DTOs\CreatePartnerContactDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerContactDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** P3-S8 — Partner contacts. */
class PartnerContactService
{
    public function getContacts(?string $partnerId = null): Collection
    {
        $query = PartnerContact::query()->orderBy('created_at', 'desc');

        if ($partnerId) {
            $this->assertPartnerAccessible($partnerId);
            $query->where('partner_id', $partnerId);
        } else {
            $ids = $this->accessiblePartnerIds();
            if ($ids->isEmpty()) {
                return collect();
            }
            $query->whereIn('partner_id', $ids);
        }

        return $query->get();
    }

    public function getContactById(string $contactId): PartnerContact
    {
        $contact = PartnerContact::query()
            ->where('partner_contact_id', $contactId)
            ->firstOrFail();

        $this->assertPartnerAccessible($contact->partner_id);

        return $contact;
    }

    public function createContact(CreatePartnerContactDTO $dto): PartnerContact
    {
        $this->assertPartnerAccessible($dto->partnerId);

        return DB::transaction(function () use ($dto) {
            if ($dto->isPrimary) {
                PartnerContact::query()
                    ->where('partner_id', $dto->partnerId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return PartnerContact::create([
                'partner_id'   => $dto->partnerId,
                'first_name'   => $dto->firstName,
                'last_name'    => $dto->lastName,
                'role_title'   => $dto->roleTitle,
                'email'        => $dto->email,
                'phone_number' => $dto->phoneNumber,
                'is_primary'   => $dto->isPrimary,
            ]);
        });
    }

    public function updateContact(string $contactId, UpdatePartnerContactDTO $dto): PartnerContact
    {
        $contact = $this->getContactById($contactId);

        return DB::transaction(function () use ($contact, $dto) {
            if ($dto->isPrimary === true) {
                PartnerContact::query()
                    ->where('partner_id', $contact->partner_id)
                    ->where('is_primary', true)
                    ->where('partner_contact_id', '!=', $contact->partner_contact_id)
                    ->update(['is_primary' => false]);
            }

            $contact->update([
                'first_name'   => $dto->firstName ?? $contact->first_name,
                'last_name'    => $dto->lastName ?? $contact->last_name,
                'role_title'   => array_key_exists('role_title', $dto->raw)
                    ? $dto->roleTitle
                    : $contact->role_title,
                'email'        => array_key_exists('email', $dto->raw)
                    ? $dto->email
                    : $contact->email,
                'phone_number' => array_key_exists('phone_number', $dto->raw)
                    ? $dto->phoneNumber
                    : $contact->phone_number,
                'is_primary'   => $dto->isPrimary ?? $contact->is_primary,
            ]);

            return $contact->fresh();
        });
    }

    public function deleteContact(string $contactId): void
    {
        $contact = $this->getContactById($contactId);
        $contact->delete();
    }

    private function assertPartnerAccessible(string $partnerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $query = Partner::query()->where('partner_id', $partnerId);

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        if (!$query->exists()) {
            throw new Exception('Partner not found or not accessible in this context.');
        }
    }

    private function accessiblePartnerIds(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $query = Partner::query()->select('partner_id');

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        return $query->pluck('partner_id');
    }
}
