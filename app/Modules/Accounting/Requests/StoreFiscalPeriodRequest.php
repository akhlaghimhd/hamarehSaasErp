<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\DTOs\FiscalPeriodDTO;

class StoreFiscalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], // مثلاً "سال مالی 1403"
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'], // پایان باید بعد از شروع باشد
            'is_closed' => ['boolean'],
        ];
    }

    public function toDTO(): FiscalPeriodDTO
    {
        return new FiscalPeriodDTO(
            name: $this->validated('name'),
            startDate: $this->validated('start_date'),
            endDate: $this->validated('end_date'),
            isClosed: $this->validated('is_closed', false)
        );
    }
}