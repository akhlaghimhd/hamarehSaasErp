<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEntityAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Iranian postal code is exactly 10 digits.
     * Accept Persian/Arabic digits from UI and normalize to ASCII before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('postal_code') && is_string($this->input('postal_code'))) {
            $raw = trim((string) $this->input('postal_code'));
            $normalized = $this->toAsciiDigits($raw);
            // strip spaces/hyphens commonly typed in postal codes
            $normalized = preg_replace('/[\s\-]/u', '', $normalized) ?? $normalized;
            $this->merge(['postal_code' => $normalized === '' ? null : $normalized]);
        }
    }

    public function rules(): array
    {
        return [
            'entity_type'     => ['required', 'string', 'max:100'],
            'entity_id'       => ['required', 'uuid'],
            // Optional: company UI may not have address_type/country catalogs yet
            'address_type_id' => ['nullable', 'uuid'],
            'country_id'      => ['nullable', 'uuid'],
            'province_id'     => ['nullable', 'uuid'],
            'city_id'         => ['nullable', 'uuid'],
            // IR national postal code: exactly 10 digits when provided
            'postal_code'    => ['nullable', 'string', 'size:10', 'regex:/^[0-9]{10}$/'],
            'address_text'    => ['required', 'string', 'max:2000'],
            'is_primary'      => ['boolean'],
            'status'          => ['integer', 'in:1,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'postal_code.size'  => 'کد پستی باید دقیقاً ۱۰ رقم باشد.',
            'postal_code.regex' => 'کد پستی باید دقیقاً ۱۰ رقم عددی باشد.',
            'address_text.required' => 'متن آدرس الزامی است.',
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
