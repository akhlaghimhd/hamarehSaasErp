<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // کد کالا (code) نباید بعد از ثبت قابل تغییر باشد (یک قانون بیزینسی)
            'name' => 'sometimes|required|string|max:255',
            'item_type' => 'sometimes|required|integer|in:1,2,3',
            'base_uom' => 'sometimes|required|string|max:50',
            'status' => 'sometimes|required|integer|in:1,2',
        ];
    }
}
