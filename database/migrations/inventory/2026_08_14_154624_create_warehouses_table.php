<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('warehouse_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->string('code', 50)->nullable(); // گاهی کد الزامیست اما در ریکوئست تست شما ارسال نشده بود
            $table->string('name', 200);
            $table->string('location', 500)->nullable();
            $table->boolean('is_active')->default(true);
            
            // فیلدهای حسابرسی و استاندارد معماری
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        // تضمین یکتایی کد انبار در هر مستأجر (در صورت وجود کد)
        DB::statement('CREATE UNIQUE INDEX uq_warehouses_tenant_code ON warehouses(tenant_id, code) WHERE deleted_at IS NULL AND code IS NOT NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
