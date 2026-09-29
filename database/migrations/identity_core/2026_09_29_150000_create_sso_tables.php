<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ID-W1-04 — SSO OIDC foundation
 *
 * tenant_sso_providers: per-tenant IdP configuration (OIDC)
 * user_sso_identities: link external subject (sub) to platform user
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sso_providers', function (Blueprint $table) {
            $table->uuid('sso_provider_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->string('code', 80)->notNull();
            $table->string('name', 200)->notNull();
            // OIDC only in foundation (SAML deferred)
            $table->string('protocol', 20)->notNull()->default('OIDC');
            $table->string('issuer', 500)->notNull();
            $table->string('authorization_endpoint', 500)->notNull();
            $table->string('token_endpoint', 500)->notNull();
            $table->string('jwks_uri', 500)->nullable();
            $table->string('client_id', 300)->notNull();
            // client_secret stored encrypted via Crypt at application layer
            $table->text('client_secret_encrypted')->nullable();
            $table->string('scopes', 300)->notNull()->default('openid profile email');
            $table->boolean('is_enabled')->notNull()->default(true);
            $table->boolean('auto_provision')->notNull()->default(false);
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_tenant_sso_providers_code ON tenant_sso_providers(tenant_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_tenant_sso_providers_tenant ON tenant_sso_providers(tenant_id) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE tenant_sso_providers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_sso_providers FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_sso_providers');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_sso_providers
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");

        Schema::create('user_sso_identities', function (Blueprint $table) {
            $table->uuid('sso_identity_id')->primary();
            $table->uuid('user_id')->notNull();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('sso_provider_id')->notNull();
            $table->string('provider_code', 80)->notNull();
            $table->string('external_subject', 300)->notNull();
            $table->string('external_email', 255)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_user_sso_identities_subject ON user_sso_identities(tenant_id, sso_provider_id, external_subject) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_user_sso_identities_user ON user_sso_identities(user_id) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE user_sso_identities ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE user_sso_identities FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON user_sso_identities');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON user_sso_identities
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON user_sso_identities');
        Schema::dropIfExists('user_sso_identities');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_sso_providers');
        Schema::dropIfExists('tenant_sso_providers');
    }
};
