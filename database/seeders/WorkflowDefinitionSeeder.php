<?php

namespace Database\Seeders;

use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Seeds SO / PO / Sales Invoice single-step approval definitions. */
class WorkflowDefinitionSeeder extends Seeder
{
    private const DEMO_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';

    public function run(): void
    {
        $tenantId = Context::get('tenant_id') ?: self::DEMO_TENANT_ID;
        $userId = Context::get('user_id') ?: (string) Str::uuid();

        Context::add('tenant_id', $tenantId);
        Context::add('user_id', $userId);

        $roleId = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', 'tenant-admin')
            ->value('tenant_role_id');
        if (!$roleId) {
            $roleId = '00000000-0000-4000-8000-000000000001';
        }

        $engine = app(WorkflowEngineService::class);

        $singleStep = function (string $taskName) use ($roleId): array {
            return [
                'initial_state' => 'pending_approval',
                'states' => [
                    'pending_approval' => [
                        'task_name'      => $taskName,
                        'assigned_type'  => WorkflowEngineService::ASSIGN_INTERNAL_ROLE,
                        'assigned_to_id' => $roleId,
                        'on_approve'     => 'approved',
                        'on_reject'      => 'rejected',
                    ],
                    'approved' => ['terminal' => true],
                    'rejected' => ['terminal' => true],
                ],
            ];
        };

        $defs = [
            ['code' => 'SALES_ORDER_APPROVAL_V1', 'name' => 'Sales Order Approval', 'target' => 'sales_orders', 'task' => 'Approve Sales Order'],
            ['code' => 'PURCHASE_ORDER_APPROVAL_V1', 'name' => 'Purchase Order Approval', 'target' => 'purchase_orders', 'task' => 'Approve Purchase Order'],
            ['code' => 'SALES_INVOICE_APPROVAL_V1', 'name' => 'Sales Invoice Approval', 'target' => 'sales_invoices', 'task' => 'Approve Sales Invoice'],
        ];

        foreach ($defs as $d) {
            $engine->upsertDefinition(
                code: $d['code'],
                name: $d['name'],
                targetAggregateType: $d['target'],
                flowGraph: $singleStep($d['task']),
                isActive: true,
            );
            Log::info('Workflow definition upserted', ['code' => $d['code'], 'tenant_id' => $tenantId]);
        }
    }
}
