<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ۱. ساخت اولیه جدول پارتنرها
        Schema::create('partners', function (Blueprint $table) {
            $table->uuid('partner_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->nullable();
            $table->uuid('parent_partner_id')->nullable();
            
            $table->string('code', 50);
            $table->string('name', 200);
            $table->smallInteger('partner_type')->default(1);
            $table->smallInteger('ownership_type')->default(1);
            $table->boolean('commission_enabled')->default(true);
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->smallInteger('status')->default(1);
            
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // ایندکس‌های یکتا و جستجو
            $table->unique(['tenant_id', 'code'], 'uq_partners_tenant_code');
            $table->index('tenant_id', 'idx_partners_tenant');
            $table->index('parent_partner_id', 'idx_partners_parent');
        });

        // ۲. افزودن کلید خارجی Self-Referencing پس از ساخت کامل جدول و کلید اصلی
        Schema::table('partners', function (Blueprint $table) {
            $table->foreign('parent_partner_id')
                  ->references('partner_id')
                  ->on('partners')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};