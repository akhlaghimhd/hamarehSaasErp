<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->uuid('profile_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            
            $table->string('national_id', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->smallInteger('gender')->nullable()->comment('1: Male, 2: Female, 3: Other');
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('description', 500)->nullable();

            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            
            $table->bigInteger('row_version')->default(1);

            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('restrict');

            $table->index('user_id', 'idx_user_profiles_user')
                  ->whereNull('deleted_at');
                  
            $table->unique('national_id', 'uq_user_profiles_national_id')
                  ->whereNull('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
