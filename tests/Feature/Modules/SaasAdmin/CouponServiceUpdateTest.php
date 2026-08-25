<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponServiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $couponService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponService = app(CouponService::class);
    }

    public function test_update_coupon(): void
    {
        $coupon = $this->couponService->createCoupon('SAVE10', CouponService::TYPE_PERCENTAGE, 10);

        $updated = $this->couponService->updateCoupon($coupon->coupon_id, status: 2, discountValue: 15);

        $this->assertEquals(2, $updated->status);
        $this->assertEquals(15, $updated->discount_value);
    }

    public function test_soft_delete_coupon(): void
    {
        $coupon = $this->couponService->createCoupon('TEMP50', CouponService::TYPE_FIXED, 50);

        $result = $this->couponService->softDeleteCoupon($coupon->coupon_id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('coupons', ['coupon_id' => $coupon->coupon_id]);
    }
}