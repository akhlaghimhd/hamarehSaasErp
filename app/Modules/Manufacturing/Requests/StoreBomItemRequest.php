<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBomItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bom_id' => ['required', 'uuid', 'exists:mfg_boms,bom_id'], // بررسی وجود سربرگ فرمول
            'item_id' => ['required', 'uuid'], // شناسه منطقی کالا از MasterData
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}