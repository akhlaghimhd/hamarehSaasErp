<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_offer_discounts', function (Blueprint $table) {
            $table->uuid('plan_offer_discount_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('plan_offer_id')->notNull();
            $table->decimal('discount_value', 20, 4)->notNull();
            $table->smallInteger('discount_type')->notNull(); // 1=percentage, 2=fixed

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
        });

        DB::statement('CREATE INDEX idx_plan_offer_discounts_offer ON plan_offer_discounts(plan_offer_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_offer_discounts');
    }
};