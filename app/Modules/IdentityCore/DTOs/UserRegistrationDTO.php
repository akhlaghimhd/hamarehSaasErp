<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UserRegistrationDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $mobile,
        public string $email,
        public string $password,
        public int $userKind = 1, // بر اساس فایل مایگریشن: 1 برای کاربر عادی
        public int $status = 1 // بر اساس فایل مایگریشن: 1 برای فعال
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            mobile: $data['mobile'],
            email: $data['email'],
            password: $data['password'],
            userKind: $data['user_kind'] ?? 1,
            status: $data['status'] ?? 1
        );
    }
}