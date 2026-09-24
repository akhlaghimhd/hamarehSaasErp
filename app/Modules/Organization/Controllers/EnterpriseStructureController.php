<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\EnterpriseStructureConfigurator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnterpriseStructureController extends Controller
{
    public function __construct(
        private readonly EnterpriseStructureConfigurator $configurator
    ) {
    }

    public function applyTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hq_name'                => 'nullable|string|max:200',
            'hq_code'                => 'nullable|string|max:50',
            'create_legal_hierarchy' => 'sometimes|boolean',
        ]);

        $result = $this->configurator->applyTemplate($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Structure template applied.',
            'data'    => $result,
        ]);
    }
}
