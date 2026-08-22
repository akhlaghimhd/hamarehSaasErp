<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // اطمینان از وجود افزونه pgcrypto برای gen_random_uuid طبق سند معماری Database
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto;');

        Schema::create('items', function (Blueprint $table) {
            // کلید اصلی بر اساس استاندارد معماری: singular_entity_name + _id
            $table->uuid('item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id'); // ایزوله‌سازی چندمستأجری
            
            // فیلدهای اصلی موجود در تست‌ها و داکیومنت
            $table->string('code', 50);
            $table->string('name', 200);
            $table->smallInteger('item_type')->default(1); // 1: Product, etc.
            $table->string('base_uom', 50)->default('PCS');
            $table->smallInteger('status')->default(1);

            // فیلدهای حسابرسی (Audit) و متادیتای الزامی پلتفرم
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        // ایجاد ایندکس یکتا روی ترکیب Tenant و Code فقط برای رکوردهای حذف‌نشده (جلوگیری از Data Bleed)
        DB::statement('CREATE UNIQUE INDEX uq_items_tenant_code ON items(tenant_id, code) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};