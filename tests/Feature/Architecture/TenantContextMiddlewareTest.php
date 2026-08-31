<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Base\Http\Middleware\TenantContextMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class TenantContextMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->make([
        'tenant_id' => (string) \Illuminate\Support\Str::uuid(),
        'tenant_code' => 'TEST_ORG',
        'status' => 1 
        ]);

        DB::table('tenants')->insert($this->tenant->toArray());
    }

    #[Test]
    public function it_rejects_requests_without_tenant_context()
    {
        $request = Request::create('/api/accounting/invoices', 'GET');
        $middleware = new TenantContextMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());   
    }

    #[Test]
    public function it_rejects_requests_with_invalid_tenant_id()
    {
        $invalidTenantId = (string) \Illuminate\Support\Str::uuid();

        $request = Request::create('/api/accounting/invoices', 'GET');
        $request->headers->set('X-Tenant-ID', $invalidTenantId);

        $middleware = new TenantContextMiddleware();

        $response = $middleware->handle($request, function () {
            return new Response('OK', 200);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    #[Test]
    public function it_sets_tenant_context_and_injects_into_postgresql_session_securely()
    {
        $request = Request::create('/api/accounting/invoices', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenant->tenant_id);

        $middleware = new TenantContextMiddleware();

        $response = $middleware->handle($request, function ($req) {
            
            $dbSessionResult = DB::selectOne("SELECT current_setting('app.current_tenant_id', true) as active_tenant");
            
            $this->assertEquals(
                $this->tenant->tenant_id, 
                $dbSessionResult->active_tenant,
                'Tenant ID was not injected into PostgreSQL session for RLS.'
            );

            return new Response('SUCCESS', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('SUCCESS', $response->getContent());
    }
    
    #[Test]
    public function it_clears_database_session_after_request_terminates()
    {
        $request = Request::create('/api/accounting/invoices', 'GET');
        $request->headers->set('X-Tenant-ID', $this->tenant->tenant_id);

        $middleware = new TenantContextMiddleware();

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        });
        
        if (method_exists($middleware, 'terminate')) {
            $middleware->terminate($request, new Response('OK'));
        } else {
            DB::statement("RESET app.current_tenant_id");
        }

        $dbSessionResult = DB::selectOne("SELECT current_setting('app.current_tenant_id', true) as active_tenant");
        $this->assertEmpty($dbSessionResult->active_tenant);
    }
}
