<?php

namespace App\Modules\Organization\DTOs;

readonly class UpdateDepartmentDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $branchId = null,              // اختیاری: اگر null باشد، شعبه فعلی حفظ می‌شود
        public ?string $parentDepartmentId = null,
        public ?string $managerUserId = null,
        public bool $isActive = true,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            code: $validatedData['code'],
            name: $validatedData['name'],
            branchId: $validatedData['branch_id'] ?? null,
            parentDepartmentId: $validatedData['parent_department_id'] ?? null,
            managerUserId: $validatedData['manager_user_id'] ?? null,
            isActive: (bool) ($validatedData['is_active'] ?? true),
        );
    }
}