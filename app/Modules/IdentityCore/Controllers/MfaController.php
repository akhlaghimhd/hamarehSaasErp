<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Services\MfaService;
use App\Modules\IdentityCore\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class MfaController extends Controller
{
    public function __construct(
        private readonly MfaService $mfa,
        private readonly AuthenticationService $auth,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('credential');

        return response()->json([
            'status'  => 'success',
            'message' => 'MFA status',
            'data'    => [
                'required'   => $this->mfa->isRequired($user),
                'enabled'    => (bool) ($user->credential?->two_factor_enabled),
                'confirmed'  => $user->credential?->two_factor_confirmed_at !== null,
            ],
        ]);
    }

    public function beginEnable(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->load('credential');
            $data = $this->mfa->beginEnable($user);

            return response()->json([
                'status'  => 'success',
                'message' => 'MFA enrollment started. Scan QR / enter secret, then confirm.',
                'data'    => $data,
            ]);
        } catch (HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function confirmEnable(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|min:6|max:16',
            ]);
            $user = $request->user();
            $user->load('credential');
            $data = $this->mfa->confirmEnable($user, $validated['code']);

            return response()->json([
                'status'  => 'success',
                'message' => 'MFA enabled. Store recovery codes securely; they are shown once.',
                'data'    => $data,
            ]);
        } catch (HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function disable(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|min:6|max:32',
            ]);
            $user = $request->user();
            $user->load('credential');
            $this->mfa->disable($user, $validated['code']);

            return response()->json([
                'status'  => 'success',
                'message' => 'MFA disabled.',
                'data'    => null,
            ]);
        } catch (HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Complete login after password step when MFA is required.
     * Expects a limited token with ability `mfa-challenge` (issued by login).
     */
    public function verifyChallenge(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code'      => 'required|string|min:6|max:32',
                'tenant_id' => 'nullable|uuid',
            ]);

            $user = $request->user();
            $user->load('credential');

            if (!$this->mfa->isRequired($user)) {
                throw new HttpException(422, 'MFA برای این کاربر لازم نیست.');
            }

            if (!$this->mfa->verifyAny($user->credential, $validated['code'])) {
                throw new HttpException(401, 'کد تأیید نامعتبر است.');
            }

            // Drop limited challenge tokens then issue full session
            $user->tokens()->where('name', 'mfa_challenge')->delete();

            $payload = $this->auth->completeLoginForUser($user, $validated['tenant_id'] ?? null);

            return response()->json([
                'status'  => 'success',
                'message' => 'ورود با موفقیت انجام شد.',
                'data'    => $payload,
            ]);
        } catch (HttpException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
