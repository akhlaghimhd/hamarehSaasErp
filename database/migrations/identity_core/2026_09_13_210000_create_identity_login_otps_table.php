<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level OTP challenges for login (pre-tenant).
 * Soft business rules: 3-minute validity, resend blocked while active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_login_otps', function (Blueprint $table) {
            $table->uuid('otp_id')->primary();
            $table->string('mobile', 20)->index();
            $table->string('code_hash', 255);
            $table->timestampTz('expires_at');
            $table->timestampTz('last_sent_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('request_ip', 45)->nullable();
            $table->timestampsTz();

            $table->index(['mobile', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_login_otps');
    }
};
