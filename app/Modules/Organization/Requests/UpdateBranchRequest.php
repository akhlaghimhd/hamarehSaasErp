<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:200'],
            'code'     => ['sometimes', 'string', 'max:100'],
            'address'  => ['nullable', 'string', 'max:500'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'status'   => ['sometimes', 'integer', 'in:0,1'],
        ];
    }
}