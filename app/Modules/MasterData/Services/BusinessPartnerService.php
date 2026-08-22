<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\BusinessPartner;
use App\Modules\MasterData\DTOs\CreateBusinessPartnerDTO;
use App\Modules\MasterData\DTOs\UpdateBusinessPartnerDTO;
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
                    'tenant_id' => $tenantId, // تزریق امن کانتکست
                    'code' => $dto->code,
                    'display_name' => $dto->display_name,
                    'partner_type' => $dto->partner_type,
                    'status' => $dto->status,
                    'parent_business_partner_id' => $dto->parent_business_partner_id,
                    'created_by' => Context::get('user_id'), // دریافت امن شناسه کاربر از کانتکست
                ]);

                // ⚡ ثبت تضمینی رویداد در Outbox در همان تراکنش
                $this->dispatchOutboxEvent('master_data.business_partner.created', $businessPartner, $tenantId);

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
                    'updated_by' => Context::get('user_id'),
                ], fn($value) => !is_null($value));

                $businessPartner->update($updateData);

                // ⚡ ثبت تضمینی رویداد ویرایش در Outbox
                $this->dispatchOutboxEvent('master_data.business_partner.updated', $businessPartner, $tenantId);

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

                // ⚡ ثبت رویداد حذف در Outbox
                $this->dispatchOutboxEvent('master_data.business_partner.deleted', $businessPartner, $tenantId);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete Business Partner: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * متد کمکی برای ثبت رویداد در جدول Outbox جهت ارتباط ناهمگام با سایر ماژول‌ها
     */
    private function dispatchOutboxEvent(string $eventType, BusinessPartner $partner, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'aggregate_type' => 'business_partners',
            'aggregate_id' => $partner->business_partner_id,
            'event_type' => $eventType,
            'payload' => json_encode($partner->toArray()),
            'status' => 1, // Pending
            'created_at' => now(),
        ]);
    }
}