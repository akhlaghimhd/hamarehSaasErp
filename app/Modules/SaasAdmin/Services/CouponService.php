<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\Coupon;
use App\Modules\SaasAdmin\Models\CouponUsage;
use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\SaasAdmin\Models\PlatformInvoice;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use InvalidArgumentException;

class CouponService
{
    public const TYPE_PERCENTAGE = 1;
    public const TYPE_FIXED = 2;

    public function createCoupon(
        string $code,
        int $discountType,
        float $discountValue,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $createdBy = null
    ): Coupon {
        $exists = Coupon::where('code', $code)->whereNull('deleted_at')->exists();
        if ($exists) {
            throw new InvalidArgumentException("Coupon code [{$code}] already exists.");
        }

        return Coupon::create([
            'code'           => $code,
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
            'status'         => 1,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'created_by'     => $createdBy,
            'updated_by'     => $createdBy,
        ]);
    }

    public function applyCoupon(
        string $couponCode,
        string $tenantId,
        float $baseAmount,
        ?string $invoiceId = null,
        ?string $createdBy = null
    ): CouponUsage {
        return DB::transaction(function () use ($couponCode, $tenantId, $baseAmount, $invoiceId, $createdBy) {
            $coupon = Coupon::where('code', $couponCode)
                ->whereNull('deleted_at')
                ->where('status', 1)
                ->firstOrFail();

            // Simple validity check
            $now = Carbon::now();
            if ($coupon->start_date && $now->lt($coupon->start_date)) {
                throw new InvalidArgumentException('Coupon is not yet valid.');
            }
            if ($coupon->end_date && $now->gt($coupon->end_date)) {
                throw new InvalidArgumentException('Coupon has expired.');
            }

            Tenant::where('tenant_id', $tenantId)->whereNull('deleted_at')->firstOrFail();

            $discountAmount = $coupon->discount_type === self::TYPE_PERCENTAGE
                ? round($baseAmount * ($coupon->discount_value / 100), 4)
                : min($coupon->discount_value, $baseAmount);

            return CouponUsage::create([
                'coupon_id'       => $coupon->coupon_id,
                'tenant_id'       => $tenantId,
                'invoice_id'      => $invoiceId,
                'discount_amount' => $discountAmount,
                'used_at'         => $now,
                'created_by'      => $createdBy,
                'updated_by'      => $createdBy,
            ]);
        });
    }
}