<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\UserRegistrationDTO;
use App\Modules\IdentityCore\Events\UserCreatedEvent;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserRegistrationService
{
    /**
     * اجرای منطق اصلی ثبت‌نام کاربر به همراه ذخیره امنیتی و الگوی Outbox
     *
     * @param UserRegistrationDTO $dto
     * @return User
     */
    public function execute(UserRegistrationDTO $dto): User
    {
        // استفاده از تراکنش دیتابیس برای تضمین یکپارچگی ثبت اطلاعات در جداول متعدد
        return DB::transaction(function () use ($dto) {

            // ۱. ثبت مشخصات اصلی کاربر در جدول users
            $user = User::create([
                'first_name' => $dto->firstName,
                'last_name'  => $dto->lastName,
                'mobile'     => $dto->mobile,
                'email'      => $dto->email,
                'user_kind'  => $dto->userKind,
                'status'     => $dto->status,
            ]);

            // ۲. ثبت اطلاعات امنیتی و احراز هویت در جدول user_credentials
            UserCredential::create([
                'user_id'             => $user->user_id, // ارجاع کلید خارجی به رکورد جدید
                'password_hash'       => Hash::make($dto->password),
                'authentication_type' => 1,
                'is_verified'         => false,
                'two_factor_enabled'  => false,
            ]);

            // ۳. ایجاد نمونه رویداد (Event)
            $event = new UserCreatedEvent($user);

            // ۴. پیاده‌سازی الگوی Event Outbox
            // درج دستی رکورد در لجر event_outbox در همان تراکنش دیتابیس (تضمین پایداری)
            DB::table('event_outbox')->insert([
                // در صورتی که tenant_id در کانتینر موجود باشد آن را می‌گیرد، در غیر این صورت یک UUID پیش‌فرض/خالی (بسته به منطق سیستم) ست می‌کند
                'tenant_id'      => app()->bound('current_tenant_id') ? app('current_tenant_id') : DB::raw('gen_random_uuid()'),
                'aggregate_type' => 'users',
                'aggregate_id'   => $user->user_id,
                'event_type'     => 'identity.user.created',
                'payload'        => json_encode($event->toPayload()),
                'status'         => 1, // 1 به معنای Pending (آماده برای پردازش توسط Worker)
                'created_at'     => now(),
            ]);

            // ۵. شلیک رویداد در حافظه لاراول (اختیاری: برای لیسنرهای همزمان داخل همین ریکوئست)
            event($event);

            Log::info("New user registered successfully with Outbox Pattern", ['user_id' => $user->user_id]);

            return $user;
        });
    }
}