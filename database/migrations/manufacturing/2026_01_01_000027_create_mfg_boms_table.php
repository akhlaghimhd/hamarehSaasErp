<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_boms', function (Blueprint $table) {
            $table->uuid('bom_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول SaaS Platform
            $table->uuid('item_id')->notNull(); // ارجاع منطقی به Inventory Item
            $table->string('version_code', 50)->notNull();
            $table->string('title', 200)->notNull();
            $table->boolean('is_active')->notNull()->default(true);
            $table->smallInteger('status')->notNull()->default(1);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants (خارج از ماژول) حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_mfg_boms_item_version ON mfg_boms(tenant_id, item_id, version_code) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_mfg_boms_item ON mfg_boms(tenant_id, item_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_boms');
    }
};