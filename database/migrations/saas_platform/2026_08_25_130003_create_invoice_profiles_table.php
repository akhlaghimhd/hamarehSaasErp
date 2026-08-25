<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_profiles', function (Blueprint $table) {
            $table->uuid('invoice_profile_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            // Logical reference to bp_partners (Master Data) - no physical FK
            $table->uuid('billing_partner_id')->notNull();
            $table->smallInteger('status')->notNull()->default(1);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            $table->foreign('tenant_id')
                  ->references('tenant_id')
                  ->on('tenants')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_invoice_profiles_tenant ON invoice_profiles(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_profiles');
    }
};