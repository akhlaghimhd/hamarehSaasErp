<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateCouponRequest;
use App\Modules\SaasAdmin\DTOs\CreateCouponDTO;
use App\Modules\SaasAdmin\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {
    }

    public function store(CreateCouponRequest $request): JsonResponse
    {
        $userId = Auth::guard('api')->id();
        $dto = CreateCouponDTO::fromRequest($request->validated());

        $start = $dto->startDate ? Carbon::parse($dto->startDate) : null;
        $end   = $dto->endDate ? Carbon::parse($dto->endDate) : null;

        $coupon = $this->couponService->createCoupon(
            $dto->code,
            $dto->discountType,
            $dto->discountValue,
            $start,
            $end,
            $userId
        );

        return response()->json([
            'message' => 'Coupon created successfully.',
            'data'    => $coupon,
        ], 201);
    }
}