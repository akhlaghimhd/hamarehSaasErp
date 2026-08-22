<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:100'],
            'decimal_places' => ['sometimes', 'integer', 'min:0'],
            'conversion_factor' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}