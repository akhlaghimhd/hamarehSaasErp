<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-INV-11 — Logical reference to Accounting voucher (no physical FK across modules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inv_documents', function (Blueprint $table) {
            $table->uuid('accounting_voucher_id')->nullable()->after('business_partner_id');
        });

        DB::statement('CREATE INDEX idx_inv_documents_accounting_voucher ON inv_documents(accounting_voucher_id) WHERE accounting_voucher_id IS NOT NULL;');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_inv_documents_accounting_voucher;');

        Schema::table('inv_documents', function (Blueprint $table) {
            $table->dropColumn('accounting_voucher_id');
        });
    }
};
