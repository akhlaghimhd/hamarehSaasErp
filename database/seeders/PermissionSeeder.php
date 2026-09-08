<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $demoTenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        $permissions = $this->getBasePermissions();

        $permissionIds = [];

        foreach ($permissions as $perm) {
            $existing = DB::table('tenant_permissions')
                ->where('tenant_id', $demoTenantId)
                ->where('code', $perm['code'])
                ->first();

            if ($existing) {
                DB::table('tenant_permissions')
                    ->where('tenant_permission_id', $existing->tenant_permission_id)
                    ->update([
                        'name'        => $perm['name'],
                        'module_name' => $perm['module_name'],
                        'action_type' => $perm['action_type'] ?? null,
                        'description' => $perm['description'] ?? null,
                        'status'      => 1,
                        'updated_at'  => now(),
                    ]);

                $permissionIds[] = $existing->tenant_permission_id;
            } else {
                $permissionId = (string) Str::uuid();

                DB::table('tenant_permissions')->insert([
                    'tenant_permission_id' => $permissionId,
                    'tenant_id'            => $demoTenantId,
                    'code'                 => $perm['code'],
                    'name'                 => $perm['name'],
                    'module_name'          => $perm['module_name'],
                    'action_type'          => $perm['action_type'] ?? null,
                    'description'          => $perm['description'] ?? null,
                    'status'               => 1,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                $permissionIds[] = $permissionId;
            }
        }

        $existingRole = DB::table('tenant_roles')
            ->where('tenant_id', $demoTenantId)
            ->where('code', 'tenant-admin')
            ->first();

        if ($existingRole) {
            $actualRoleId = $existingRole->tenant_role_id;

            DB::table('tenant_roles')
                ->where('tenant_role_id', $actualRoleId)
                ->update([
                    'name'        => 'Tenant Administrator',
                    'description' => 'Full access role for tenant administrators',
                    'status'      => 1,
                    'updated_at'  => now(),
                ]);
        } else {
            $actualRoleId = (string) Str::uuid();

            DB::table('tenant_roles')->insert([
                'tenant_role_id' => $actualRoleId,
                'tenant_id'      => $demoTenantId,
                'code'           => 'tenant-admin',
                'name'           => 'Tenant Administrator',
                'description'    => 'Full access role for tenant administrators',
                'status'         => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $existingRolePerms = DB::table('tenant_role_permissions')
            ->where('tenant_role_id', $actualRoleId)
            ->pluck('tenant_permission_id')
            ->all();

        $toAttach = array_values(array_diff($permissionIds, $existingRolePerms));

        if (!empty($toAttach)) {
            $insertData = [];
            foreach ($toAttach as $permissionId) {
                $insertData[] = [
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_role_id'            => $actualRoleId,
                    'tenant_permission_id'      => $permissionId,
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ];
            }
            DB::table('tenant_role_permissions')->insert($insertData);
        }
    }

    private function getBasePermissions(): array
    {
        return [
            ['code' => 'identity.user.view', 'name' => 'View Users', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.user.create', 'name' => 'Create Users', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.user.update', 'name' => 'Update Users', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.role.view', 'name' => 'View Roles', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.role.manage', 'name' => 'Manage Roles', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],

            ['code' => 'masterdata.business-partner.view', 'name' => 'View Business Partners', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'masterdata.business-partner.create', 'name' => 'Create Business Partner', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'masterdata.business-partner.update', 'name' => 'Update Business Partner', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],

            ['code' => 'inventory.item.view', 'name' => 'View Items', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.item.create', 'name' => 'Create Item', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.warehouse.view', 'name' => 'View Warehouses', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.document.view', 'name' => 'View Inventory Documents', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.document.create', 'name' => 'Create Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.document.post', 'name' => 'Post Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'EXECUTE'],

            ['code' => 'accounting.voucher.view', 'name' => 'View Vouchers', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.voucher.post', 'name' => 'Post Voucher', 'module_name' => 'Accounting', 'action_type' => 'EXECUTE'],
            ['code' => 'accounting.account.view', 'name' => 'View Accounts', 'module_name' => 'Accounting', 'action_type' => 'READ'],

            ['code' => 'procurement.purchase-order.create', 'name' => 'Create Purchase Order', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-receipt.create', 'name' => 'Create Purchase Receipt', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-receipt.view', 'name' => 'View Purchase Receipt', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-receipt.post', 'name' => 'Post Purchase Receipt', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.sales-order.create', 'name' => 'Create Sales Order', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-order.view', 'name' => 'View Sales Order', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-order.confirm', 'name' => 'Confirm Sales Order', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.sales-delivery.create', 'name' => 'Create Sales Delivery', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-delivery.view', 'name' => 'View Sales Delivery', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-delivery.post', 'name' => 'Post Sales Delivery', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.sales-quotation.create', 'name' => 'Create Sales Quotation', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.return-order.create', 'name' => 'Create Return Order', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],

            ['code' => 'procurement.sales-invoice.view', 'name' => 'View Sales Invoices', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-invoice.create', 'name' => 'Create Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-invoice.update', 'name' => 'Update Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'UPDATE'],
            ['code' => 'procurement.sales-invoice.post', 'name' => 'Post Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.purchase-invoice.view', 'name' => 'View Purchase Invoices', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-invoice.create', 'name' => 'Create Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-invoice.post', 'name' => 'Post Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],

            ['code' => 'procurement.payment-schedule.view', 'name' => 'View Payment Schedules', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.cash-transaction.create', 'name' => 'Create Cash Transactions', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.cash-transaction.view', 'name' => 'View Cash Transactions', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],

            ['code' => 'procurement.purchase-requisition.view', 'name' => 'View Purchase Requisitions', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-requisition.create', 'name' => 'Create Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-requisition.submit', 'name' => 'Submit Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.purchase-requisition.approve', 'name' => 'Approve Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],

            ['code' => 'workflow.definition.manage', 'name' => 'Manage Workflow Definitions', 'module_name' => 'Workflow', 'action_type' => 'EXECUTE'],
            ['code' => 'workflow.instance.start', 'name' => 'Start Workflow Instance', 'module_name' => 'Workflow', 'action_type' => 'EXECUTE'],
            ['code' => 'workflow.instance.view', 'name' => 'View Workflow Instance', 'module_name' => 'Workflow', 'action_type' => 'READ'],
            ['code' => 'workflow.task.view', 'name' => 'View Workflow Worklist', 'module_name' => 'Workflow', 'action_type' => 'READ'],
            ['code' => 'workflow.task.complete', 'name' => 'Complete Workflow Task', 'module_name' => 'Workflow', 'action_type' => 'EXECUTE'],
        ];
    }
}
