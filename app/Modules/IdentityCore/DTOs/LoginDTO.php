<?php

namespace App\Modules\IdentityCore\DTOs;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class LoginDTO
{
    public readonly string $email;
    public readonly string $password;
    public readonly ?string $tenantId;

    public function __construct(string $email, string $password, ?string $tenantId = null)
    {
        $this->email = $email;
        $this->password = $password;
        $this->tenantId = $tenantId;
    }

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'tenant_id' => 'nullable|string' // Optional at login, user might have multiple tenants
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return new self(
            $validated['email'],
            $validated['password'],
            $validated['tenant_id'] ?? null
        );
    }
}