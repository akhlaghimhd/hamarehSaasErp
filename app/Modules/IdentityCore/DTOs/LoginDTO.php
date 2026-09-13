<?php

namespace App\Modules\IdentityCore\DTOs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Password login: identifier = email OR mobile. tenant_id is optional system field
 * (never typed by end-user; used only after organization selection).
 */
class LoginDTO
{
    public readonly string $identifier;
    public readonly string $password;
    public readonly ?string $tenantId;

    public function __construct(string $identifier, string $password, ?string $tenantId = null)
    {
        $this->identifier = $identifier;
        $this->password = $password;
        $this->tenantId = $tenantId;
    }

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            // Backward compatible: accept `email` or `identifier`
            'identifier' => 'nullable|string|max:191',
            'email'      => 'nullable|string|max:191',
            'password'   => 'required|string|min:6',
            'tenant_id'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? ''));

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => ['شناسه ورود (ایمیل یا موبایل) الزامی است.'],
            ]);
        }

        return new self(
            $identifier,
            $validated['password'],
            $validated['tenant_id'] ?? null
        );
    }
}
