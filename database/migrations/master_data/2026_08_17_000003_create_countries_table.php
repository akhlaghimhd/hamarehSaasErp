<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('country_id')->primary()->default(DB::raw('gen_random_uuid()'));
            
            $table->string('iso_code', 10);
            $table->string('iso_numeric_code', 3)->nullable(); // اعمال اصلاحیه معماری
            $table->string('name', 200);
            $table->string('phone_code', 20)->nullable();
            $table->smallInteger('status')->default(1);
            
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('deleted_at')->nullable();

            // ایندکس یونیک بدون نیاز به Tenant_id
            $table->unique('iso_code', 'uq_countries_iso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};