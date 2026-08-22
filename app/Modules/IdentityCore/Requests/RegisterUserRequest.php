<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * در این مرحله چون کاربر در حال ثبت نام است، نیازی به لاگین بودن نیست
     * * @return bool
     */
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'mobile'     => ['nullable', 'string', 'max:50'], // در مایگریشن nullable است، اما اگر الزامی است تغییر دهید
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'], // فرض می‌کنیم فیلد password_confirmation هم ارسال می‌شود
            'user_kind'  => ['nullable', 'integer', 'in:1,2,3'], // بر اساس نوع کاربران تعریف شده
        ];
    }

    /**
     * Get custom messages for validator errors.
     * (اختیاری: برای شخصی سازی پیام‌های خطا)
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'این ایمیل قبلاً در سیستم ثبت شده است.',
        ];
    }
}