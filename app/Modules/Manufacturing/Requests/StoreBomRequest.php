<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Manufacturing\DTOs\BomDTO;
use App\Modules\Manufacturing\DTOs\BomItemDTO;

class StoreBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'uuid'],
            'bom_code' => ['required', 'string', 'max:100'],
            'version_number' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'batch_size' => ['required', 'numeric', 'min:0.0001'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'integer', 'in:1,2,3'], // 1: Draft, 2: Approved, 3: Obsolete
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_item_id' => ['required', 'uuid'],
            'items.*.uom_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.scrap_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function toDTO(): BomDTO
    {
        $itemsDTO = array_map(function ($item) {
            return new BomItemDTO(
                component_item_id: $item['component_item_id'],
                uom_id: $item['uom_id'],
                quantity: (float) $item['quantity'],
                scrap_percentage: (float) $item['scrap_percentage'],
                sort_order: (int) $item['sort_order']
            );
        }, $this->validated('items'));

        return new BomDTO(
            item_id: $this->validated('item_id'),
            bom_code: $this->validated('bom_code'),
            version_number: (int) $this->validated('version_number'),
            is_active: (bool) $this->validated('is_active'),
            batch_size: (float) $this->validated('batch_size'),
            description: $this->validated('description'),
            status: (int) $this->validated('status'),
            created_by: auth()->id(),
            items: $itemsDTO
        );
    }
}