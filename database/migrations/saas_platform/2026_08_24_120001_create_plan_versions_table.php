<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_versions', function (Blueprint $table) {
            $table->uuid('plan_version_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('plan_id')->notNull();
            $table->integer('version_number')->notNull();
            $table->smallInteger('status')->notNull()->default(1);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            // Physical FK allowed inside same Bounded Context (Layer 1)
            $table->foreign('plan_id')
                  ->references('plan_id')
                  ->on('plans')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_plan_version ON plan_versions(plan_id, version_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_plan_versions_plan ON plan_versions(plan_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_versions');
    }
};