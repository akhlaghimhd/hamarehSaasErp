<?php

namespace App\Modules\SaasPlatform\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'           => ['required', 'string', 'max:100'],
            'discount_type'  => ['required', 'integer', 'in:1,2'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}