<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_available_addons', function (Blueprint $table) {
            $table->uuid('offer_available_addon_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('plan_offer_id')->notNull();
            $table->uuid('addon_id')->notNull();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            $table->foreign('plan_offer_id')
                  ->references('plan_offer_id')
                  ->on('plan_offers')
                  ->onDelete('restrict');

            $table->foreign('addon_id')
                  ->references('addon_id')
                  ->on('addons')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_offer_addon ON offer_available_addons(plan_offer_id, addon_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_available_addons');
    }
};