<?php

namespace App\Modules\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartProcessInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'definition_code'        => ['required', 'string', 'max:100'],
            'target_aggregate_type'  => ['required', 'string', 'max:100'],
            'target_aggregate_id'    => ['required', 'uuid'],
            'context_snapshot'       => ['nullable', 'array'],
        ];
    }
}
