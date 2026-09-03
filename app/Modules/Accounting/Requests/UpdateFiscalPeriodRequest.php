<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFiscalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'string', 'max:150'],
            'start_date' => ['sometimes', 'date'],
            'end_date'   => ['sometimes', 'date', 'after:start_date'],
        ];
    }
}
