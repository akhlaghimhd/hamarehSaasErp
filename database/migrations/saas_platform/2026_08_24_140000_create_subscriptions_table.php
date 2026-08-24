<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('subscription_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            $table->uuid('plan_version_id')->notNull();
            $table->smallInteger('status')->notNull()->default(1);
            $table->timestampTz('start_date')->nullable();
            $table->timestampTz('end_date')->nullable();
            $table->timestampTz('next_billing_date')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            // Physical FK allowed inside same Bounded Context (Layer 1)
            $table->foreign('tenant_id')
                  ->references('tenant_id')
                  ->on('tenants')
                  ->onDelete('restrict');

            $table->foreign('plan_version_id')
                  ->references('plan_version_id')
                  ->on('plan_versions')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_subscriptions_tenant ON subscriptions(tenant_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_subscriptions_version ON subscriptions(plan_version_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};