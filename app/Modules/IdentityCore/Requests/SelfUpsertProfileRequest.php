<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelfUpsertProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ~20 words Persian
            'display_bio' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
