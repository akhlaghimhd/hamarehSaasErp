<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_users', function (Blueprint $table) {
            $table->uuid('partner_user_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id')->notNull();
            $table->uuid('user_id')->notNull(); // Logical Reference to Identity Core users

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('partner_id')->references('partner_id')->on('partners')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_partner_users ON partner_users(partner_id, user_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_users');
    }
};