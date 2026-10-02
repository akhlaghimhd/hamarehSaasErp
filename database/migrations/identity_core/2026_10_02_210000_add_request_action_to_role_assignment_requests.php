<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dual-control: GRANT vs REVOKE pending requests.
 * When tenant requires approval, revoke of existing roles also goes through queue
 * so current access is not disrupted until approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_role_assignment_requests')) {
            return;
        }

        Schema::table('tenant_role_assignment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_role_assignment_requests', 'request_action')) {
                $table->string('request_action', 20)->default('GRANT')->after('tenant_role_id');
                // GRANT | REVOKE
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenant_role_assignment_requests')) {
            return;
        }

        Schema::table('tenant_role_assignment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_role_assignment_requests', 'request_action')) {
                $table->dropColumn('request_action');
            }
        });
    }
};
