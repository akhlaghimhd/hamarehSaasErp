<?php

namespace Database\Factories;

use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantUserFactory extends Factory
{
    protected $model = TenantUser::class;

    public function definition(): array
    {
        return [
            'tenant_user_id' => (string) Str::uuid(),
            'tenant_id'      => Tenant::factory(),
            'user_id'        => User::factory(),
            'status'         => 1,
        ];
    }
}