<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Smart Hierarchy D3: node_origin SYSTEM|MANUAL on erp_org_hierarchy_nodes.
 * Idempotent: safe if column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('erp_org_hierarchy_nodes')) {
            return;
        }
        if (!Schema::hasColumn('erp_org_hierarchy_nodes', 'node_origin')) {
            Schema::table('erp_org_hierarchy_nodes', function (Blueprint $table) {
                $table->string('node_origin', 20)->default('SYSTEM')->after('entity_id');
            });
        }
        DB::table('erp_org_hierarchy_nodes')
            ->whereNull('node_origin')
            ->orWhere('node_origin', '')
            ->update(['node_origin' => 'SYSTEM']);
    }

    public function down(): void
    {
        if (Schema::hasTable('erp_org_hierarchy_nodes') && Schema::hasColumn('erp_org_hierarchy_nodes', 'node_origin')) {
            Schema::table('erp_org_hierarchy_nodes', function (Blueprint $table) {
                $table->dropColumn('node_origin');
            });
        }
    }
};
