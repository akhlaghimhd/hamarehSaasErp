<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\SystemSetting;
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

    #[Test]
    public function it_ensures_catalog_defaults_including_retention_keys(): void
    {
        $created = $this->service->ensureDefaults();

        $this->assertGreaterThanOrEqual(4, $created);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => SystemSetting::KEY_PLATFORM_EMAIL_BASE_DOMAIN,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => SystemSetting::KEY_RETENTION_SOFT_DELETE_DAYS_DEFAULT,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => SystemSetting::KEY_RETENTION_SOFT_DELETE_DAYS_ORG_MASTERS,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => SystemSetting::KEY_RETENTION_PURGE_JOB_ENABLED,
        ]);

        // Second call is idempotent — no overwrite / no duplicate active rows
        $again = $this->service->ensureDefaults();
        $this->assertSame(0, $again);

        $this->assertSame(90, $this->service->getInt(SystemSetting::KEY_RETENTION_SOFT_DELETE_DAYS_DEFAULT));
        $this->assertFalse($this->service->getBool(SystemSetting::KEY_RETENTION_PURGE_JOB_ENABLED));
    }

    #[Test]
    public function typed_getters_return_defaults_when_missing(): void
    {
        $this->assertNull($this->service->getValue('missing.key'));
        $this->assertSame('fallback', $this->service->getValue('missing.key', 'fallback'));
        $this->assertFalse($this->service->getBool('missing.bool'));
        $this->assertTrue($this->service->getBool('missing.bool', true));
        $this->assertSame(7, $this->service->getInt('missing.int', 7));
    }
}
