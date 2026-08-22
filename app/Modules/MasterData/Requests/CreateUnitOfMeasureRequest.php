<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'decimal_places' => ['integer', 'min:0'],
            'conversion_factor' => ['numeric', 'min:0'],
            'status' => ['integer', 'in:1,2'],
        ];
    }
}