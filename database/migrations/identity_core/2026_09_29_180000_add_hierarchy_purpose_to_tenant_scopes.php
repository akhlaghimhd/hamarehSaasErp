<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID-W2-04 — Bind scopes to org hierarchy purpose + optional subtree expansion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_scopes')) {
            return;
        }

        Schema::table('tenant_scopes', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_scopes', 'hierarchy_purpose')) {
                $table->string('hierarchy_purpose', 40)->nullable()->after('reference_id');
            }
            if (!Schema::hasColumn('tenant_scopes', 'include_subtree')) {
                $table->boolean('include_subtree')->default(false)->after('hierarchy_purpose');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenant_scopes')) {
            return;
        }

        Schema::table('tenant_scopes', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_scopes', 'include_subtree')) {
                $table->dropColumn('include_subtree');
            }
            if (Schema::hasColumn('tenant_scopes', 'hierarchy_purpose')) {
                $table->dropColumn('hierarchy_purpose');
            }
        });
    }
};
