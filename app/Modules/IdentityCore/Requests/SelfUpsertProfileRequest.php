<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service profile fields only.
 * Identity fields (national_id, gender, birth_date, phone, …) are admin-managed.
 * address is accepted as a change *request* (pending manager approval).
 */
class SelfUpsertProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // display bio under avatar (stored in description column)
            'display_bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'], // alias
            'address'     => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
