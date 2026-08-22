<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxDefinitionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tax_category_id' => ['sometimes', 'uuid'],
            'code' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:200'],
            'tax_type' => ['sometimes', 'integer'],
            'calculation_type' => ['sometimes', 'integer', 'in:1,2'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}