<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntityAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('postal_code') && is_string($this->input('postal_code'))) {
            $raw = trim((string) $this->input('postal_code'));
            $normalized = $this->toAsciiDigits($raw);
            $normalized = preg_replace('/[\s\-]/u', '', $normalized) ?? $normalized;
            $this->merge(['postal_code' => $normalized === '' ? null : $normalized]);
        }
    }

    public function rules(): array
    {
        return [
            'entity_type'     => ['sometimes', 'string', 'max:100'],
            'entity_id'       => ['sometimes', 'uuid'],
            'address_type_id' => ['sometimes', 'nullable', 'uuid'],
            'country_id'      => ['sometimes', 'nullable', 'uuid'],
            'province_id'     => ['nullable', 'uuid'],
            'city_id'         => ['nullable', 'uuid'],
            'postal_code'    => ['nullable', 'string', 'size:10', 'regex:/^[0-9]{10}$/'],
            'address_text'    => ['sometimes', 'string', 'max:2000'],
            'is_primary'      => ['sometimes', 'boolean'],
            'status'          => ['sometimes', 'integer', 'in:1,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'postal_code.size'  => 'کد پستی باید دقیقاً ۱۰ رقم باشد.',
            'postal_code.regex' => 'کد پستی باید دقیقاً ۱۰ رقم عددی باشد.',
        ];
    }

    private function toAsciiDigits(string $value): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace([...$fa, ...$ar], [...$en, ...$en], $value);
    }
}
