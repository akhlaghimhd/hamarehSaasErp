<?php

namespace Tests\Feature\Modules\SaasPlatform;

use App\Modules\SaasPlatform\Services\AddonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonServiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private AddonService $addonService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addonService = app(AddonService::class);
    }

    public function test_update_addon(): void
    {
        $addon = $this->addonService->createAddon('EXTRA', 'Extra Storage');

        $updated = $this->addonService->updateAddon($addon->addon_id, 'Extra Storage 20GB', 1);

        $this->assertEquals('Extra Storage 20GB', $updated->name);
    }

    public function test_soft_delete_addon(): void
    {
        $addon = $this->addonService->createAddon('TEMP', 'Temporary');

        $result = $this->addonService->softDeleteAddon($addon->addon_id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('addons', ['addon_id' => $addon->addon_id]);
    }
}