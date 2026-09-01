<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L5-DM-02 follow-up: DocumentSequence model uses SoftDeletes
 * but original create migration lacked deleted_at / deleted_by columns.
 * This caused SQL errors (400) on any query using the SoftDeletes global scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            if (!Schema::hasColumn('document_sequences', 'deleted_at')) {
                $table->timestampTz('deleted_at')->nullable()->after('updated_by');
            }
            if (!Schema::hasColumn('document_sequences', 'deleted_by')) {
                $table->uuid('deleted_by')->nullable()->after('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            if (Schema::hasColumn('document_sequences', 'deleted_by')) {
                $table->dropColumn('deleted_by');
            }
            if (Schema::hasColumn('document_sequences', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
