<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L3 SoftDeletes closure (2026-09-09):
 * Add SoftDeletes + full audit columns to partner_contacts, partner_documents, partner_bank_accounts.
 * partner_activity_logs remains append-only by design (SSOT).
 * Aligns with Law 1.4 / 3.5 and updated Database Layer 3 SSOT v1.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        // partner_contacts
        Schema::table('partner_contacts', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('created_at');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->timestampTz('deleted_at')->nullable()->after('updated_by');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
        });

        DB::statement('DROP INDEX IF EXISTS idx_partner_contacts_parent');
        DB::statement('CREATE INDEX idx_partner_contacts_parent ON partner_contacts (partner_id) WHERE deleted_at IS NULL');

        // partner_documents
        Schema::table('partner_documents', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('created_at');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->timestampTz('deleted_at')->nullable()->after('updated_by');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
        });

        DB::statement('DROP INDEX IF EXISTS idx_partner_docs_parent');
        DB::statement('CREATE INDEX idx_partner_docs_parent ON partner_documents (partner_id) WHERE deleted_at IS NULL');

        // partner_bank_accounts
        Schema::table('partner_bank_accounts', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('created_at');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->timestampTz('deleted_at')->nullable()->after('updated_by');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
        });

        // Recreate unique index to also exclude soft-deleted rows
        DB::statement('DROP INDEX IF EXISTS uq_partner_bank_shaba');
        DB::statement(
            'CREATE UNIQUE INDEX uq_partner_bank_shaba ON partner_bank_accounts (shaba_number) WHERE is_active = TRUE AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        // partner_contacts
        DB::statement('DROP INDEX IF EXISTS idx_partner_contacts_parent');
        Schema::table('partner_contacts', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'updated_by', 'deleted_at', 'deleted_by']);
        });
        DB::statement('CREATE INDEX idx_partner_contacts_parent ON partner_contacts (partner_id)');

        // partner_documents
        DB::statement('DROP INDEX IF EXISTS idx_partner_docs_parent');
        Schema::table('partner_documents', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'updated_by', 'deleted_at', 'deleted_by']);
        });
        DB::statement('CREATE INDEX idx_partner_docs_parent ON partner_documents (partner_id)');

        // partner_bank_accounts
        DB::statement('DROP INDEX IF EXISTS uq_partner_bank_shaba');
        Schema::table('partner_bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'updated_by', 'deleted_at', 'deleted_by']);
        });
        DB::statement(
            'CREATE UNIQUE INDEX uq_partner_bank_shaba ON partner_bank_accounts (shaba_number) WHERE is_active = TRUE'
        );
    }
};
