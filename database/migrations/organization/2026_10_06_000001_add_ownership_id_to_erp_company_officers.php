<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional logical link: officer ↔ ownership row (same company).
 * No physical FK across concerns; UUID only (Law 2.2 / 2.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('erp_company_officers')) {
            return;
        }
        if (Schema::hasColumn('erp_company_officers', 'ownership_id')) {
            return;
        }

        Schema::table('erp_company_officers', function (Blueprint $table) {
            $table->uuid('ownership_id')->nullable()->after('person_user_id');
            $table->index(['tenant_id', 'ownership_id'], 'idx_erp_company_officers_ownership');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('erp_company_officers')) {
            return;
        }
        if (! Schema::hasColumn('erp_company_officers', 'ownership_id')) {
            return;
        }

        Schema::table('erp_company_officers', function (Blueprint $table) {
            $table->dropIndex('idx_erp_company_officers_ownership');
            $table->dropColumn('ownership_id');
        });
    }
};
