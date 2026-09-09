<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L2-M06 – Complete audit trail on notification_templates
 * Adds created_by / updated_by / deleted_by to match SoftDeletes + audit standard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('created_at');
            $table->uuid('updated_by')->nullable()->after('updated_at');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'updated_by', 'deleted_by']);
        });
    }
};
