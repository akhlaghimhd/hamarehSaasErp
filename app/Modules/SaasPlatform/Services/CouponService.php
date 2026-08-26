<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\Models\Coupon;
use App\Modules\SaasPlatform\Models\CouponUsage;
use App\Modules\SaasPlatform\Models\Tenant;
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

    public function updateCoupon(
        string $couponId,
        ?int $status = null,
        ?float $discountValue = null,
        ?Carbon $endDate = null,
        ?string $updatedBy = null
    ): Coupon {
        $coupon = Coupon::where('coupon_id', $couponId)->whereNull('deleted_at')->firstOrFail();

        if ($status !== null) {
            $coupon->status = $status;
        }
        if ($discountValue !== null) {
            $coupon->discount_value = $discountValue;
        }
        if ($endDate !== null) {
            $coupon->end_date = $endDate;
        }
        $coupon->updated_by = $updatedBy;
        $coupon->save();

        return $coupon->fresh();
    }

    public function softDeleteCoupon(string $couponId, ?string $deletedBy = null): bool
    {
        $coupon = Coupon::where('coupon_id', $couponId)->whereNull('deleted_at')->firstOrFail();

        $coupon->deleted_by = $deletedBy;
        $coupon->save();

        return (bool) $coupon->delete();
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