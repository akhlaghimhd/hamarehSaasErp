<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\DTOs\CreateTenantDTO;
use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\SaasAdmin\Models\TenantUser;
use Illuminate\Support\Facades\DB;

class TenantService
{
    /**
     * ایجاد یک مستأجر (شرکت) جدید و تخصیص کاربر سازنده به عنوان مالک آن
     */
    public function createTenant(CreateTenantDTO $dto, string $userId): Tenant
    {
        return DB::transaction(function () use ($dto, $userId) {
            // 1. ساخت شرکت جدید
            $tenant = Tenant::create([
                'name'   => $dto->name,
                'domain' => $dto->domain,
                'status' => 1, // فعال
            ]);

            // 2. ثبت کاربر به عنوان مالک شرکت
            TenantUser::create([
                'tenant_id' => $tenant->tenant_id,
                'user_id'   => $userId,
                'is_owner'  => true,
                'status'    => 1, // فعال
            ]);

            //TODO: انتشار رویداد TenantCreated.v1 برای ساخت دیتای پایه (مثلا سال مالی پیش‌فرض) در آینده

            return $tenant;
        });
    }
}