<?php

namespace Tests\Feature\Modules\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-16 — Final architecture rules audit for Inventory module.
 * - No physical FK across modules (only inv_* ↔ inv_*)
 * - NUMERIC(20,4) for quantity/cost columns
 * - Partial unique indexes include tenant_id and deleted_at IS NULL where applicable
 * - Soft delete + row_version on operational entities
 * - tenant_id on all operational tables
 */
class InventoryArchitectureRulesAuditTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATIONAL_TABLES = [
        'inv_items',
        'inv_warehouses',
        'inv_locations',
        'inv_stock_batches',
        'inv_stock_balances',
        'inv_documents',
        'inv_document_items',
    ];

    #[Test]
    public function all_operational_tables_have_tenant_id(): void
    {
        foreach (self::OPERATIONAL_TABLES as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'tenant_id'),
                "Table {$table} must have tenant_id (Rule 1.1)."
            );
        }
    }

    #[Test]
    public function soft_delete_columns_exist_on_master_and_document_tables(): void
    {
        foreach (['inv_items', 'inv_warehouses', 'inv_locations', 'inv_stock_batches', 'inv_documents'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'deleted_at'), "{$table}.deleted_at missing");
            $this->assertTrue(Schema::hasColumn($table, 'deleted_by'), "{$table}.deleted_by missing");
        }
    }

    #[Test]
    public function row_version_exists_on_operational_tables(): void
    {
        foreach (self::OPERATIONAL_TABLES as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'row_version'),
                "Table {$table} must have row_version (Rule 1.5)."
            );
        }
    }

    #[Test]
    public function quantity_columns_use_numeric_20_4(): void
    {
        $columns = [
            ['inv_stock_balances', 'quantity_on_hand'],
            ['inv_stock_balances', 'quantity_reserved'],
            ['inv_stock_balances', 'quantity_available'],
            ['inv_document_items', 'quantity'],
            ['inv_document_items', 'unit_cost'],
            ['inv_document_items', 'total_cost'],
            ['inv_stock_batches', 'quantity_produced'],
            ['inv_stock_batches', 'quantity_remaining'],
        ];

        foreach ($columns as [$table, $column]) {
            $meta = DB::selectOne(
                "SELECT data_type, numeric_precision, numeric_scale
                 FROM information_schema.columns
                 WHERE table_schema = 'public' AND table_name = ? AND column_name = ?",
                [$table, $column]
            );

            $this->assertNotNull($meta, "Column {$table}.{$column} not found");
            $this->assertSame('numeric', $meta->data_type, "{$table}.{$column} must be numeric");
            $this->assertSame(20, (int) $meta->numeric_precision, "{$table}.{$column} precision must be 20");
            $this->assertSame(4, (int) $meta->numeric_scale, "{$table}.{$column} scale must be 4");
        }
    }

    #[Test]
    public function physical_foreign_keys_only_reference_inventory_tables(): void
    {
        // NOTE: PostgreSQL LIKE treats '_' as single-char wildcard, so
        // LIKE 'inv_%' also matches invoice_* — use regex ^inv_ instead.
        $fks = DB::select(
            "SELECT
                tc.table_name,
                kcu.column_name,
                ccu.table_name AS foreign_table_name
             FROM information_schema.table_constraints AS tc
             JOIN information_schema.key_column_usage AS kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
             JOIN information_schema.constraint_column_usage AS ccu
               ON ccu.constraint_name = tc.constraint_name
              AND ccu.table_schema = tc.table_schema
             WHERE tc.constraint_type = 'FOREIGN KEY'
               AND tc.table_schema = 'public'
               AND tc.table_name ~ '^inv_'"
        );

        $this->assertNotEmpty($fks, 'Expected intra-module FKs on inv_* tables');

        foreach ($fks as $fk) {
            $this->assertStringStartsWith(
                'inv_',
                $fk->foreign_table_name,
                "Physical FK {$fk->table_name}.{$fk->column_name} → {$fk->foreign_table_name} "
                . 'violates Rule 2.2 (cross-module physical FK forbidden).'
            );
        }
    }

    #[Test]
    public function logical_cross_module_columns_have_no_physical_fk(): void
    {
        $logicalColumns = [
            ['inv_documents', 'fiscal_period_id'],
            ['inv_documents', 'business_partner_id'],
            ['inv_documents', 'source_document_id'],
            ['inv_documents', 'accounting_voucher_id'],
            ['inv_items', 'item_group_id'],
            ['inv_items', 'uom_id'],
            ['inv_warehouses', 'branch_id'],
        ];

        $fkSet = [];
        $fks = DB::select(
            "SELECT tc.table_name, kcu.column_name
             FROM information_schema.table_constraints AS tc
             JOIN information_schema.key_column_usage AS kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
             WHERE tc.constraint_type = 'FOREIGN KEY'
               AND tc.table_schema = 'public'
               AND tc.table_name ~ '^inv_'"
        );
        foreach ($fks as $fk) {
            $fkSet[$fk->table_name . '.' . $fk->column_name] = true;
        }

        foreach ($logicalColumns as [$table, $column]) {
            if (!Schema::hasColumn($table, $column)) {
                continue;
            }
            $this->assertArrayNotHasKey(
                $table . '.' . $column,
                $fkSet,
                "{$table}.{$column} must remain a logical UUID (no physical FK)."
            );
        }
    }

    #[Test]
    public function partial_unique_indexes_use_deleted_at_null_predicate(): void
    {
        $indexes = DB::select(
            "SELECT indexname, indexdef
             FROM pg_indexes
             WHERE schemaname = 'public'
               AND tablename ~ '^inv_'
               AND indexdef ILIKE '%UNIQUE%'"
        );

        $softDeleteTables = ['inv_items', 'inv_warehouses', 'inv_locations', 'inv_stock_batches', 'inv_documents'];

        foreach ($indexes as $idx) {
            $def = $idx->indexdef;
            $isSoftDeleteTable = false;
            foreach ($softDeleteTables as $table) {
                if (str_contains($def, $table)) {
                    $isSoftDeleteTable = true;
                    break;
                }
            }

            if (!$isSoftDeleteTable) {
                continue;
            }

            if (str_contains($def, 'uq_') || str_contains(strtolower($def), 'unique index uq_')) {
                $this->assertStringContainsStringIgnoringCase(
                    'deleted_at',
                    $def,
                    "Unique index {$idx->indexname} on soft-delete table should filter deleted_at IS NULL. Got: {$def}"
                );
            }
        }
    }

    #[Test]
    public function generated_columns_are_present(): void
    {
        $this->assertTrue(Schema::hasColumn('inv_stock_balances', 'quantity_available'));
        $this->assertTrue(Schema::hasColumn('inv_document_items', 'total_cost'));

        $available = DB::selectOne(
            "SELECT is_generated
             FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = 'inv_stock_balances'
               AND column_name = 'quantity_available'"
        );
        $this->assertNotNull($available);
        $this->assertSame('ALWAYS', $available->is_generated);

        $totalCost = DB::selectOne(
            "SELECT is_generated
             FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = 'inv_document_items'
               AND column_name = 'total_cost'"
        );
        $this->assertNotNull($totalCost);
        $this->assertSame('ALWAYS', $totalCost->is_generated);
    }

    #[Test]
    public function rls_is_enabled_and_forced_on_operational_tables(): void
    {
        foreach (self::OPERATIONAL_TABLES as $table) {
            $row = DB::selectOne(
                'SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = \'public\' AND c.relname = ?',
                [$table]
            );

            $this->assertNotNull($row, "Table {$table} not found in pg_class");
            $this->assertTrue((bool) $row->rls_enabled, "RLS must be ENABLED on {$table}");
            $this->assertTrue((bool) $row->rls_forced, "RLS must be FORCED on {$table}");
        }
    }
}
