<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('userId') ?? $this->user()?->user_id;

        return [
            'national_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('user_profiles', 'national_id')
                    ->whereNull('deleted_at')
                    ->ignore($userId, 'user_id'),
            ],
            'birth_date'  => ['sometimes', 'nullable', 'date', 'before:today'],
            'avatar_url'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'gender'      => ['sometimes', 'nullable', 'integer', 'in:1,2,3'],
            'address'     => ['sometimes', 'nullable', 'string'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
