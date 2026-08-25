<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('coupon_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code', 100)->notNull();
            $table->smallInteger('discount_type')->notNull(); // 1=percentage, 2=fixed
            $table->decimal('discount_value', 20, 4)->notNull();
            $table->smallInteger('status')->notNull()->default(1);
            $table->timestampTz('start_date')->nullable();
            $table->timestampTz('end_date')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_coupons_code ON coupons(code) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};