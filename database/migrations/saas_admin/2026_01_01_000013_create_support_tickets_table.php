<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('ticket_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            $table->uuid('tenant_user_id')->nullable(); // Logical ref to Layer 4
            $table->uuid('assigned_admin_user_id')->nullable(); // Logical ref to admin_users
            $table->string('ticket_number', 50)->notNull();
            $table->string('subject', 300)->notNull();
            $table->text('description')->nullable();
            $table->smallInteger('priority')->notNull()->default(2);
            $table->smallInteger('status')->notNull()->default(1);
            $table->string('channel', 50)->notNull()->default('PORTAL');
            $table->uuid('category_id')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_support_tickets_number ON support_tickets(ticket_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_support_tickets_tenant_user ON support_tickets(tenant_user_id);');
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
