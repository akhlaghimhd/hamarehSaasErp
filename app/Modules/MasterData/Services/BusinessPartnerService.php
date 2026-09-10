<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\BusinessPartner;
use App\Modules\MasterData\DTOs\CreateBusinessPartnerDTO;
use App\Modules\MasterData\DTOs\UpdateBusinessPartnerDTO;
use App\Modules\MasterData\Events\BusinessPartnerCreatedV1;
use App\Modules\MasterData\Events\BusinessPartnerUpdatedV1;
use App\Modules\MasterData\Events\BusinessPartnerDeletedV1;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class BusinessPartnerService
{
    public function getAllBusinessPartners(): Collection
    {
        // اعمال اتوماتیک فیلتر مستأجر توسط TenantScoped Trait انجام می‌شود
        return BusinessPartner::all();
    }

    public function getBusinessPartnerById(string $id): BusinessPartner
    {
        return BusinessPartner::findOrFail($id);
    }

    public function createBusinessPartner(CreateBusinessPartnerDTO $dto): BusinessPartner
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                $businessPartner = BusinessPartner::create([
                    'tenant_id' => $tenantId,
                    'code' => $dto->code,
                    'display_name' => $dto->display_name,
                    'partner_type' => $dto->partner_type,
                    'status' => $dto->status,
                    'parent_business_partner_id' => $dto->parent_business_partner_id,
                    'credit_limit' => $dto->credit_limit,
                    'created_by' => Context::get('user_id'),
                ]);

                $event = new BusinessPartnerCreatedV1(
                    businessPartnerId: $businessPartner->business_partner_id,
                    tenantId: $tenantId,
                    code: $businessPartner->code,
                    displayName: $businessPartner->display_name,
                    partnerType: (int) $businessPartner->partner_type,
                    createdBy: Context::get('user_id'),
                );

                $this->dispatchOutboxEvent($event::EVENT_TYPE, $event->toPayload(), $businessPartner, $tenantId);

                return $businessPartner;
            });
        } catch (Exception $e) {
            Log::error('Failed to create Business Partner: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateBusinessPartner(string $id, UpdateBusinessPartnerDTO $dto): BusinessPartner
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $businessPartner = BusinessPartner::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $updateData = array_filter([
                    'display_name' => $dto->display_name,
                    'partner_type' => $dto->partner_type,
                    'status' => $dto->status,
                    'parent_business_partner_id' => $dto->parent_business_partner_id,
                    'credit_limit' => $dto->credit_limit,
                    'updated_by' => Context::get('user_id'),
                ], fn ($value) => ! is_null($value));

                $businessPartner->update($updateData);

                $event = new BusinessPartnerUpdatedV1(
                    businessPartnerId: $businessPartner->business_partner_id,
                    tenantId: $tenantId,
                    code: $businessPartner->code,
                    displayName: $businessPartner->display_name,
                    partnerType: (int) $businessPartner->partner_type,
                    status: (int) $businessPartner->status,
                    updatedBy: Context::get('user_id'),
                );

                $this->dispatchOutboxEvent($event::EVENT_TYPE, $event->toPayload(), $businessPartner, $tenantId);

                return $businessPartner;
            });
        } catch (Exception $e) {
            Log::error('Failed to update Business Partner: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteBusinessPartner(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $businessPartner = BusinessPartner::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $businessPartner->update(['deleted_by' => Context::get('user_id')]);
                $businessPartner->delete();

                $event = new BusinessPartnerDeletedV1(
                    businessPartnerId: $businessPartner->business_partner_id,
                    tenantId: $tenantId,
                    code: $businessPartner->code,
                    deletedBy: Context::get('user_id'),
                );

                $this->dispatchOutboxEvent($event::EVENT_TYPE, $event->toPayload(), $businessPartner, $tenantId);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete Business Partner: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ثبت رویداد نسخه‌دار در جدول Outbox جهت ارتباط ناهمگام با سایر ماژول‌ها
     */
    private function dispatchOutboxEvent(string $eventType, array $payload, BusinessPartner $partner, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'aggregate_type' => 'business_partners',
            'aggregate_id' => $partner->business_partner_id,
            'event_type' => $eventType,
            'payload' => json_encode($payload),
            'status' => 1, // Pending
            'created_at' => now(),
        ]);
    }
}
