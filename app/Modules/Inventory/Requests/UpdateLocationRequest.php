<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = Context::get('tenant_id');

        return [
            'name' => 'sometimes|required|string|max:200',
            'parent_location_id' => [
                'nullable',
                'uuid',
                Rule::exists('inv_locations', 'location_id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'aisle'  => 'nullable|string|max:50',
            'rack'   => 'nullable|string|max:50',
            'shelf'  => 'nullable|string|max:50',
            'status' => 'sometimes|required|integer|in:1,2',
        ];
    }
}
