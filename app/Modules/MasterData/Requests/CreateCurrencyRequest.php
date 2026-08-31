<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string', 'max:10'],
            'name'       => ['required', 'string', 'max:255'],
            'symbol'     => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'status'     => ['sometimes', 'boolean'],
        ];
    }
}
