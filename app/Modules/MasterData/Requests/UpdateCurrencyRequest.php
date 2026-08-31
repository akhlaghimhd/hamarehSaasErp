<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'       => ['sometimes', 'string', 'max:10'],
            'name'       => ['sometimes', 'string', 'max:255'],
            'symbol'     => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'status'     => ['sometimes', 'boolean'],
        ];
    }
}
