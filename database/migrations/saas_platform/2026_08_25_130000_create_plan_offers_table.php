<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_offers', function (Blueprint $table) {
            $table->uuid('plan_offer_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('plan_version_id')->notNull();
            $table->string('name', 200)->notNull();
            $table->smallInteger('status')->notNull()->default(1);
            $table->timestampTz('start_date')->nullable();
            $table->timestampTz('end_date')->nullable();

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
        });

        DB::statement('CREATE INDEX idx_plan_offers_version ON plan_offers(plan_version_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_offers');
    }
};