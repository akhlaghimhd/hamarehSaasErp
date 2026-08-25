<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\SaasAdmin\Models\Coupon;
use App\Modules\SaasAdmin\Models\CouponUsage;
use App\Modules\SaasAdmin\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $couponService;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponService = app(CouponService::class);
        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'COUPON_TEST',
            'status'      => 1,
        ]);
    }

    public function test_create_coupon(): void
    {
        $coupon = $this->couponService->createCoupon('SAVE20', CouponService::TYPE_PERCENTAGE, 20.0000);

        $this->assertDatabaseHas('coupons', [
            'coupon_id'      => $coupon->coupon_id,
            'code'           => 'SAVE20',
            'discount_type'  => 1,
            'discount_value' => 20.0000,
        ]);
    }

    public function test_apply_coupon_percentage(): void
    {
        $this->couponService->createCoupon('SAVE10', CouponService::TYPE_PERCENTAGE, 10.0000);

        $usage = $this->couponService->applyCoupon('SAVE10', $this->tenant->tenant_id, 200.0000);

        $this->assertEquals(20.0000, $usage->discount_amount);
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_usage_id' => $usage->coupon_usage_id,
            'tenant_id'       => $this->tenant->tenant_id,
            'discount_amount' => 20.0000,
        ]);
    }

    public function test_apply_coupon_fixed(): void
    {
        $this->couponService->createCoupon('FLAT50', CouponService::TYPE_FIXED, 50.0000);

        $usage = $this->couponService->applyCoupon('FLAT50', $this->tenant->tenant_id, 200.0000);

        $this->assertEquals(50.0000, $usage->discount_amount);
    }
}