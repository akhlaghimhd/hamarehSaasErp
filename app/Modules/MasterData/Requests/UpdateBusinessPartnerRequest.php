<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // کد (code) قابل ویرایش نیست (Business Rule)
            'display_name' => 'sometimes|required|string|max:200',
            'partner_type' => 'sometimes|required|integer|in:1,2',
            'status' => 'sometimes|required|integer|in:1,2,3',
            'parent_business_partner_id' => 'nullable|uuid|exists:business_partners,business_partner_id',
        ];
    }
}