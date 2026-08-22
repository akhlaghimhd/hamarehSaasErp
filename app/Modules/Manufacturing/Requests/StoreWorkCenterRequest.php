<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Manufacturing\DTOs\WorkCenterDTO;

class StoreWorkCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'capacity_hours_per_day' => ['required', 'numeric', 'min:0'],
            'efficiency_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'cost_per_hour' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'integer', 'in:1,2,3'],
        ];
    }

    public function toDTO(): WorkCenterDTO
    {
        return new WorkCenterDTO(
            code: $this->validated('code'),
            name: $this->validated('name'),
            capacity_hours_per_day: (float) $this->validated('capacity_hours_per_day'),
            efficiency_percentage: (float) $this->validated('efficiency_percentage'),
            cost_per_hour: (float) $this->validated('cost_per_hour'),
            status: (int) $this->validated('status'),
            created_by: auth()->id() ?? '' // جلوگیری از نال بودن در محیط‌های بدون auth (تست)
        );
    }
}