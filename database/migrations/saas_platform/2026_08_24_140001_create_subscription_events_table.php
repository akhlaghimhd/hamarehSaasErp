<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->uuid('subscription_event_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('subscription_id')->notNull();
            $table->smallInteger('event_type')->notNull();
            $table->string('description', 500)->nullable();
            $table->timestampTz('event_date')->notNull()->default(DB::raw('NOW()'));

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
        });

        DB::statement('CREATE INDEX idx_subscription_events_sub ON subscription_events(subscription_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};