<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional hierarchy for tenant roles (parent → children).
 * Same Bounded Context → physical FK allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_roles', 'parent_role_id')) {
                $table->uuid('parent_role_id')->nullable()->after('tenant_id');
                $table->index('parent_role_id', 'idx_tenant_roles_parent_role_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_roles', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_roles', 'parent_role_id')) {
                $table->dropIndex('idx_tenant_roles_parent_role_id');
                $table->dropColumn('parent_role_id');
            }
        });
    }
};
