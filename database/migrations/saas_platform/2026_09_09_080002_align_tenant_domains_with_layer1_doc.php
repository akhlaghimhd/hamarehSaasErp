<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L1-04 – Align tenant_domains columns with Database Layer 1 document.
 *
 * Adds fields present in the official schema that were missing:
 *   domain_type, verification_token, verified_at, ssl_status
 *
 * PK remains tenant_domain_id (code convention) rather than domain_id
 * to avoid breaking existing references; document may be updated later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_domains', 'domain_type')) {
                $table->smallInteger('domain_type')->notNull()->default(1)->after('domain_name');
            }
            if (!Schema::hasColumn('tenant_domains', 'verification_token')) {
                $table->string('verification_token', 255)->nullable()->after('is_primary');
            }
            if (!Schema::hasColumn('tenant_domains', 'verified_at')) {
                $table->timestampTz('verified_at')->nullable()->after('verification_token');
            }
            if (!Schema::hasColumn('tenant_domains', 'ssl_status')) {
                $table->smallInteger('ssl_status')->notNull()->default(0)->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->dropColumn(['domain_type', 'verification_token', 'verified_at', 'ssl_status']);
        });
    }
};
