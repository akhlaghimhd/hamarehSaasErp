<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inv_item_barcodes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE inv_item_barcodes FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON inv_item_barcodes
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON inv_item_barcodes');
        DB::statement('ALTER TABLE inv_item_barcodes NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE inv_item_barcodes DISABLE ROW LEVEL SECURITY');
    }
};
