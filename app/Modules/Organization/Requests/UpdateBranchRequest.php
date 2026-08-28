<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'max:50'],
            'name'      => ['required', 'string', 'max:200'],
            'address'   => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}