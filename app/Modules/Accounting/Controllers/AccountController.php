<?php

namespace App\Modules\Accounting\Controllers;

use App\Base\Controller;
use App\Modules\Accounting\Requests\StoreAccountRequest;
use App\Modules\Accounting\Services\AccountService;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function store(StoreAccountRequest $request, AccountService $service): JsonResponse
    {
        // کنترلر لاغر: فقط تبدیل به DTO و فراخوانی Service
        $dto = $request->toDTO();
        
        $account = $service->createAccount($dto);

        return response()->json([
            'success' => true,
            'message' => 'Chart of Account created successfully.',
            'data' => [
                'account_id' => $account->account_id,
                'code' => $account->code,
                'level' => $account->level,
            ]
        ], 201);
    }
}