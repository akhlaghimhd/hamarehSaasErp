<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds the missing 'type' column required by CostCenter model/service/DTO.
     * 1: Company, 2: Branch, 3: Department, 4: CostCenter, 5: Project
     */
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            if (!Schema::hasColumn('cost_centers', 'type')) {
                $table->smallInteger('type')->default(4)->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            if (Schema::hasColumn('cost_centers', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
