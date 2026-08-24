<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_version_features', function (Blueprint $table) {
            $table->uuid('plan_version_feature_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('plan_version_id')->notNull();
            $table->uuid('plan_feature_id')->notNull();
            $table->boolean('enabled')->notNull()->default(true);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            $table->foreign('plan_version_id')
                  ->references('plan_version_id')
                  ->on('plan_versions')
                  ->onDelete('restrict');

            $table->foreign('plan_feature_id')
                  ->references('plan_feature_id')
                  ->on('plan_features')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_plan_version_feature ON plan_version_features(plan_version_id, plan_feature_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_version_features');
    }
};