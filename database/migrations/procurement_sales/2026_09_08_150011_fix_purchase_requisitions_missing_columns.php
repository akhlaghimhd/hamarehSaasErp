<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair path: table may have been created incomplete (without priority)
 * when an earlier partial migration ran before the full L6-PS-09 definition.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_requisitions')) {
            return;
        }

        Schema::table('purchase_requisitions', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requisitions', 'priority')) {
                $table->smallInteger('priority')->notNull()->default(2)->after('required_date');
            }
            if (!Schema::hasColumn('purchase_requisitions', 'status')) {
                $table->smallInteger('status')->notNull()->default(1)->after('priority');
            }
            if (!Schema::hasColumn('purchase_requisitions', 'description')) {
                $table->string('description', 500)->nullable()->after('status');
            }
            if (!Schema::hasColumn('purchase_requisitions', 'row_version')) {
                $table->unsignedBigInteger('row_version')->default(1);
            }
        });
    }

    public function down(): void
    {
        // non-destructive repair; no down
    }
};
