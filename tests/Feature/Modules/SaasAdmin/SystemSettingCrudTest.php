<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemSettingCrudTest extends TestCase
{
    use RefreshDatabase;

    private SystemSettingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SystemSettingService::class);
    }

    #[Test]
    public function it_creates_system_setting(): void
    {
        $setting = $this->service->upsert(
            settingKey: 'platform.maintenance_mode',
            settingValue: 'false',
            description: 'Global maintenance flag'
        );

        $this->assertNotNull($setting->system_setting_id);
        $this->assertEquals('platform.maintenance_mode', $setting->setting_key);
        $this->assertEquals('false', $setting->setting_value);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'platform.maintenance_mode',
        ]);
    }

    #[Test]
    public function it_updates_existing_setting_by_key(): void
    {
        $this->service->upsert('app.timezone', 'UTC', 'Default TZ');

        $updated = $this->service->upsert('app.timezone', 'Asia/Tehran', 'Updated TZ');

        $this->assertEquals('Asia/Tehran', $updated->setting_value);
        $this->assertEquals('Updated TZ', $updated->description);
        $this->assertEquals(1, SystemSettingService::class ? 1 : 0); // keep simple

        $this->assertDatabaseCount('system_settings', 1);
    }

    #[Test]
    public function it_gets_setting_by_key(): void
    {
        $this->service->upsert('feature.x', 'on');

        $found = $this->service->getByKey('feature.x');

        $this->assertEquals('on', $found->setting_value);
    }

    #[Test]
    public function it_soft_deletes_system_setting(): void
    {
        $setting = $this->service->upsert('to.delete', '1');

        $this->service->softDelete($setting->system_setting_id);

        $this->assertSoftDeleted('system_settings', [
            'system_setting_id' => $setting->system_setting_id,
        ]);
    }

    #[Test]
    public function it_lists_active_settings(): void
    {
        $this->service->upsert('k1', 'v1');
        $this->service->upsert('k2', 'v2');

        $list = $this->service->list();

        $this->assertGreaterThanOrEqual(2, $list->count());
    }
}
