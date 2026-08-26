<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateCouponRequest;
use App\Modules\SaasAdmin\DTOs\CreateCouponDTO;
use App\Modules\SaasAdmin\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {
    }

    public function store(CreateCouponRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $dto = CreateCouponDTO::fromRequest($request->validated());
        $start = $dto->startDate ? Carbon::parse($dto->startDate) : null;
        $end   = $dto->endDate ? Carbon::parse($dto->endDate) : null;

        $coupon = $this->couponService->createCoupon(
            $dto->code,
            $dto->discountType,
            $dto->discountValue,
            $start,
            $end,
            $user->user_id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Coupon created successfully.',
            'data'    => $coupon,
        ], 201);
    }
}