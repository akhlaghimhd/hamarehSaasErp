<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->uuid('plan_price_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('plan_version_id')->notNull();
            $table->decimal('amount', 20, 4)->notNull();
            $table->integer('billing_period_days')->notNull();
            $table->smallInteger('status')->notNull()->default(1);

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

        DB::statement('CREATE INDEX idx_plan_prices_version ON plan_prices(plan_version_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};