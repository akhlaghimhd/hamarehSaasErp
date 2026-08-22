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
        // ۱. ساخت اولیه جدول business_partners
        Schema::create('business_partners', function (Blueprint $table) {
            $table->uuid('business_partner_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->string('code', 50);
            $table->string('display_name', 200);
            $table->smallInteger('partner_type')->default(1); // 1: Individual, 2: Organization
            $table->smallInteger('status')->default(1);       // 1: Active, 2: Suspended, 3: Blocked
            
            $table->uuid('parent_business_partner_id')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // ایندکس‌های یکتا و بهینه‌سازی کوئری
            $table->unique(['tenant_id', 'code'], 'uq_business_partners_tenant_code');
            $table->index('tenant_id', 'idx_business_partners_tenant');
            $table->index('display_name', 'idx_business_partners_display_name');
            $table->index('parent_business_partner_id', 'idx_business_partners_parent');
        });

        // ۲. افزودن کلید خارجی Self-Referencing پس از ایجاد کامل جدول و کلید اصلی
        Schema::table('business_partners', function (Blueprint $table) {
            $table->foreign('parent_business_partner_id')
                  ->references('business_partner_id')
                  ->on('business_partners')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_partners');
    }
};