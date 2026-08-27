<?php

namespace Database\Factories;

use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $companyName = $this->faker->unique()->company;

        return [
            'tenant_id'              => Str::uuid()->toString(),
            'tenant_name'            => $companyName,
            'tenant_code'            => 'TENANT_' . strtoupper($this->faker->unique()->lexify('????')),
            'slug'                   => Str::slug($companyName),
            'legal_name'             => $companyName . ' Ltd',
            'tenant_type'            => 1,
            'primary_domain_enabled' => false,
            'domain_status'          => 1,
            'status'                 => 1,
        ];
    }
}