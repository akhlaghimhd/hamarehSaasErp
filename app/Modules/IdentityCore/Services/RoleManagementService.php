<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateRoleDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Base\Services\EventBusService;
use Illuminate\Support\Facades\DB;

class RoleManagementService
{
    public function __construct(
        private readonly EventBusService $eventBus
    ) {}

    /**
     * ایجاد نقش جدید و اتصال پرمیشن‌ها در یک ترانزکشن
     */
    public function createRole(CreateRoleDTO $dto): TenantRole
    {
        return DB::transaction(function () use ($dto) {
            $role = TenantRole::create([
                'role_name' => $dto->roleName,
                'description' => $dto->description,
                'status' => 1, // Active
                // فیلد tenant_id به صورت خودکار توسط ترِیت TenantScoped پر می‌شود
            ]);

            // اگر شناسه پرمیشنی ارسال شده بود، آن‌ها را به نقش متصل کن
            if (!empty($dto->permissionIds)) {
                $role->permissions()->sync($dto->permissionIds);
            }

            return $role;
        });
    }

    /**
     * تخصیص نقش(ها) به کاربر و شلیک رویداد در Outbox
     */
    public function assignRoleToUser(AssignRoleToUserDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            foreach ($dto->roleIds as $roleId) {
                // جلوگیری از ثبت نقش تکراری برای یک کاربر خاص
                $userRole = TenantUserRole::firstOrCreate([
                    'user_id' => $dto->userId,
                    'tenant_role_id' => $roleId,
                ]);

                // شلیک رویداد ناهمگام (Integration Event) از طریق Event Bus
                // بر اساس مستندات (Core Identity Layer) ایونتِ RoleAssigned.v1 باید ثبت شود
                if ($userRole->wasRecentlyCreated) {
                    $this->eventBus->publish(
                        aggregateType: 'users',
                        aggregateId: $dto->userId,
                        eventType: 'Identity.RoleAssigned.v1',
                        payload: [
                            'user_id' => $dto->userId,
                            'tenant_role_id' => $roleId,
                            'assigned_at' => now()->toIso8601String()
                        ]
                    );
                }
            }
        });
    }
}