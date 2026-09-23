<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class OrgP7HardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function organization_permission_catalog_codes_are_unique_and_prefixed(): void
    {
        $seeder = new PermissionSeeder();
        $ref = new ReflectionClass($seeder);
        $method = $ref->getMethod('organizationCatalog');
        $method->setAccessible(true);
        /** @var list<array{code:string}> $catalog */
        $catalog = $method->invoke($seeder);

        $codes = array_column($catalog, 'code');
        $this->assertSame(count($codes), count(array_unique($codes)));

        foreach ($codes as $code) {
            $this->assertStringStartsWith('organization.', $code);
        }

        $this->assertContains('organization.company.view', $codes);
        $this->assertContains('organization.business_unit.manage', $codes);
        $this->assertContains('organization.intercompany.manage', $codes);
        $this->assertContains('organization.structure.configure', $codes);
    }

    #[Test]
    public function permission_seeder_inserts_organization_catalog_for_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_P7',
            'status'      => 1,
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenant->tenant_id]);

        $this->seed(PermissionSeeder::class);

        $this->assertTrue(
            DB::table('tenant_permissions')
                ->where('tenant_id', $tenant->tenant_id)
                ->where('code', 'organization.company.view')
                ->exists()
        );

        $this->assertTrue(
            DB::table('tenant_permissions')
                ->where('tenant_id', $tenant->tenant_id)
                ->where('code', 'organization.structure.configure')
                ->exists()
        );
    }
}
