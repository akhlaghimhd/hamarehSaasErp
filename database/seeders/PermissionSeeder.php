<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->permissions();

        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')
                ->where('code', $permission['code'])
                ->exists();

            if (!$exists) {
                DB::table('permissions')->insert([
                    'permission_id' => (string) Str::uuid(),
                    'code' => $permission['code'],
                    'name' => $permission['name'],
                    'module_name' => $permission['module_name'],
                    'action_type' => $permission['action_type'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Log::info('PermissionSeeder completed', ['count' => count($permissions)]);
    }

    private function permissions(): array
    {
        return [
            // NOTE: Full restored list is in artifacts/PermissionSeeder_L6PS09.php —
            // This push is a safety minimal set for PS module if full push fails.
            // User should replace with full seeder from local artifact if other modules missing.
            ['code' => 'procurement.purchase-requisition.view', 'name' => 'View Purchase Requisitions', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-requisition.create', 'name' => 'Create Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-requisition.submit', 'name' => 'Submit Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.purchase-requisition.approve', 'name' => 'Approve Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.payment-schedule.view', 'name' => 'View Payment Schedules', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.cash-transaction.create', 'name' => 'Create Cash Transactions', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.cash-transaction.view', 'name' => 'View Cash Transactions', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-invoice.view', 'name' => 'View Sales Invoices', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-invoice.create', 'name' => 'Create Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-invoice.update', 'name' => 'Update Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'UPDATE'],
            ['code' => 'procurement.sales-invoice.post', 'name' => 'Post Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.purchase-invoice.view', 'name' => 'View Purchase Invoices', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-invoice.create', 'name' => 'Create Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-invoice.post', 'name' => 'Post Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
        ];
    }
}
