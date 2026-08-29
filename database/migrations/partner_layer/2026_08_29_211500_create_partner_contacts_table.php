<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D8 — partner_contacts (Database Layer 3 SSOT).
 * partner_id: physical FK within PartnerLayer bounded context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_contacts', function (Blueprint $table) {
            $table->uuid('partner_contact_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('role_title', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('partner_id')
                ->references('partner_id')
                ->on('partners')
                ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_partner_contacts_parent ON partner_contacts (partner_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contacts');
    }
};
