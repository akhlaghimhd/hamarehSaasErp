<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-FIN-002 / FIN-CSN-F0-03, F0-04
 * - draft_number retained after post (entry_number becomes JE-…)
 * - unique (tenant, company, entry_number) / (tenant, company, draft_number) where not null
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_acc_journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('fin_acc_journal_entries', 'draft_number')) {
                $table->string('draft_number', 40)->nullable()->after('entry_number');
            }
        });

        // Backfill: existing drafts / posted rows that only have entry_number
        DB::statement("
            UPDATE fin_acc_journal_entries
            SET draft_number = entry_number
            WHERE draft_number IS NULL
              AND entry_number IS NOT NULL
              AND status = 'DRAFT'
        ");

        DB::statement('DROP INDEX IF EXISTS uq_fin_acc_je_entry_number_company');
        DB::statement('DROP INDEX IF EXISTS uq_fin_acc_je_draft_number_company');

        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_je_entry_number_company
            ON fin_acc_journal_entries (tenant_id, company_id, entry_number)
            WHERE entry_number IS NOT NULL AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_je_draft_number_company
            ON fin_acc_journal_entries (tenant_id, company_id, draft_number)
            WHERE draft_number IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_fin_acc_je_entry_number_company');
        DB::statement('DROP INDEX IF EXISTS uq_fin_acc_je_draft_number_company');

        Schema::table('fin_acc_journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('fin_acc_journal_entries', 'draft_number')) {
                $table->dropColumn('draft_number');
            }
        });
    }
};
