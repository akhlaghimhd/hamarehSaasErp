<?php

namespace Database\Factories;

use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\SaasAdmin\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantRoleFactory extends Factory
{
    protected $model = TenantRole::class;

    public function definition(): array
    {
        return [
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'code' => 'ROLE_' . strtoupper($this->faker->unique()->word()),
            'name' => $this->faker->word() . ' Role',
            'status' => 1, 
        ];
    }
}