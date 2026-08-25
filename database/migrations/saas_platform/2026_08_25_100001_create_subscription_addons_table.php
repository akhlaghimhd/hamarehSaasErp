<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->uuid('subscription_addon_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('subscription_id')->notNull();
            $table->uuid('addon_id')->notNull();
            $table->decimal('amount', 20, 4)->notNull();
            $table->smallInteger('status')->notNull()->default(1);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            $table->foreign('subscription_id')
                  ->references('subscription_id')
                  ->on('subscriptions')
                  ->onDelete('restrict');

            $table->foreign('addon_id')
                  ->references('addon_id')
                  ->on('addons')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_subscription_addons_sub ON subscription_addons(subscription_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_subscription_addons_addon ON subscription_addons(addon_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_addons');
    }
};