<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_wallets', function (Blueprint $table) {
            $table->uuid('wallet_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            $table->decimal('balance', 20, 4)->notNull()->default(0);
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

        DB::statement('CREATE UNIQUE INDEX uq_tenant_wallet ON tenant_wallets(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_wallets');
    }
};