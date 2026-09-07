<?php

namespace App\Modules\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertProcessDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                   => ['required', 'string', 'max:100'],
            'name'                   => ['required', 'string', 'max:200'],
            'target_aggregate_type'  => ['required', 'string', 'max:100'],
            'flow_graph'             => ['required', 'array'],
            'flow_graph.initial_state' => ['required', 'string'],
            'flow_graph.states'      => ['required', 'array'],
            'is_active'              => ['nullable', 'boolean'],
        ];
    }
}
