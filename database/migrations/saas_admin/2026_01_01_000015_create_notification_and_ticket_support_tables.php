<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('template_code', 100)->notNull();
            $table->string('title', 200)->notNull();
            $table->text('body_template')->notNull();
            $table->string('channel', 50)->notNull(); // SMS, EMAIL, PUSH, IN_APP
            $table->boolean('is_active')->notNull()->default(true);
            $table->timestampTz('created_at')->notNull()->default(DB::raw('NOW()'));
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });
        DB::statement('CREATE UNIQUE INDEX uq_notification_templates_code ON notification_templates(template_code) WHERE deleted_at IS NULL;');

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('delivery_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('notification_id')->notNull();
            $table->string('channel', 50)->notNull();
            $table->smallInteger('status')->notNull()->default(1); // 1 Pending, 2 Sent, 3 Failed, 4 Delivered
            $table->text('error_message')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->integer('retry_count')->notNull()->default(0);
        });
        DB::statement('CREATE INDEX idx_notification_deliveries_status ON notification_deliveries(status) WHERE status = 3;');

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->uuid('message_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('ticket_id')->notNull();
            $table->smallInteger('sender_type')->notNull(); // 1 Tenant User, 2 Admin Support
            $table->uuid('sender_user_id')->notNull();
            $table->text('message_body')->notNull();
            $table->timestampTz('created_at')->notNull()->default(DB::raw('NOW()'));

            $table->foreign('ticket_id')->references('ticket_id')->on('support_tickets')->onDelete('cascade');
        });
        DB::statement('CREATE INDEX idx_support_messages_ticket ON support_ticket_messages(ticket_id);');

        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->uuid('attachment_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('message_id')->notNull();
            $table->string('file_name', 255)->notNull();
            $table->string('storage_path', 1000)->notNull();
            $table->bigInteger('file_size_bytes')->notNull();
            $table->timestampTz('created_at')->notNull()->default(DB::raw('NOW()'));

            $table->foreign('message_id')->references('message_id')->on('support_ticket_messages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
    }
};
