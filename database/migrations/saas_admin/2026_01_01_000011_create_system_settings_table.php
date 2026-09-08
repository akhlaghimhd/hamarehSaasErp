<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->uuid('system_setting_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('setting_key', 150)->notNull();
            $table->text('setting_value')->nullable();
            $table->string('description', 500)->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_system_settings_key ON system_settings(setting_key) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
