<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaxDefinitionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tax_category_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'tax_type' => ['required', 'integer'],
            'calculation_type' => ['integer', 'in:1,2'],
            'tax_rate' => ['numeric', 'min:0'],
            'status' => ['integer', 'in:1,2'],
        ];
    }
}