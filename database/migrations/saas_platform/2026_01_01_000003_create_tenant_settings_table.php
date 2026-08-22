<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->uuid('tenant_setting_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            $table->string('setting_key', 100)->notNull();
            $table->text('setting_value')->nullable();
            $table->string('setting_group', 50)->notNull()->default('GENERAL');

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_tenant_settings_key ON tenant_settings(tenant_id, setting_key) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};