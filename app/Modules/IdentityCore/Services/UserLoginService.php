<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\UserLoginDTO;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class UserLoginService
{
    /**
     * اجرای فرآیند لاگین
     *
     * @param UserLoginDTO $dto
     * @return array شامل توکن و اطلاعات کاربر
     * @throws ValidationException
     */
    public function execute(UserLoginDTO $dto): array
    {
        $credentials = [
            'email'    => $dto->email,
            'password' => $dto->password,
        ];

        // تلاش برای لاگین با استفاده از درایور احراز هویت (مبتنی بر JWT)
        if (! $token = Auth::guard('api')->attempt($credentials)) {
            // انتشار رویداد لاگین ناموفق (بر اساس قوانین Event Outbox در آینده می‌تواند اینجا اضافه شود)
            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور اشتباه است.'],
            ]);
        }

        // دریافت مدل کاربری که با موفقیت لاگین کرده است
        $user = Auth::guard('api')->user();

        // آپدیت فیلد آخرین زمان ورود
        $user->update([
            'last_login_at' => Carbon::now(),
        ]);

        //TODO: ذخیره رویداد UserLoginSucceeded.v1 در جدول event_outbox در مراحل بعدی

        return [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            'user'         => [
                'id'    => $user->user_id ?? $user->id,
                'email' => $user->email,
                // در آینده نقش‌ها و Scope‌ها در اینجا یا داخل ادعاهای (Claims) توکن قرار می‌گیرد
            ]
        ];
    }
}