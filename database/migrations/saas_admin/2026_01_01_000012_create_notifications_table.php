<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('notification_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            $table->uuid('recipient_user_id')->notNull(); // Logical ref to identity users
            $table->string('title', 200)->notNull();
            $table->text('body')->notNull();
            $table->string('type_code', 50)->notNull();
            $table->boolean('is_read')->notNull()->default(false);
            $table->timestampTz('read_at')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE INDEX idx_notifications_recipient ON notifications(tenant_id, recipient_user_id, is_read) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
