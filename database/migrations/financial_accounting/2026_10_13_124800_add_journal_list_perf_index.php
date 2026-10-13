<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIN list perf — covering index for journals list (company + soft-delete + created_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_acc_journal_entries', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'company_id', 'deleted_at', 'created_at'],
                'idx_fin_acc_je_list_scope'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fin_acc_journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_fin_acc_je_list_scope');
        });
    }
};
