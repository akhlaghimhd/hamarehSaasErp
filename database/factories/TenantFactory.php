<?php

namespace Database\Factories;

use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    // ظ…طھطµظ„ ع©ط±ط¯ظ† ط¯ظ‚غŒظ‚ ظپع©طھظˆط±غŒ ط¨ظ‡ ظ…ط¯ظ„ Tenant ط¯ط± ظ„ط§غŒظ‡ ظ…ط§عکظˆظ„â€Œظ‡ط§
    protected $model = Tenant::class;

    public function definition(): array
    {
        $companyName = $this->faker->unique()->company;

        return [
            // ط³ط§ط®طھ UUID ط§ط®طھطµط§طµغŒ ط¨ط±ط§غŒ ط´ظ†ط§ط³ظ‡ طھظ†ظ†طھ
            'tenant_id'              => Str::uuid()->toString(),

            // ط§طµظ„ط§ط­ ظ†ط§ظ… ط³طھظˆظ† ط§ط² name ط¨ظ‡ tenant_name ط¨ط± ط§ط³ط§ط³ ظ…ط¹ظ…ط§ط±غŒ ظ¾ط§غŒع¯ط§ظ‡ ط¯ط§ط¯ظ‡
            'tenant_name'            => $companyName,

            // طھظˆظ„غŒط¯ ظپغŒظ„ط¯ظ‡ط§غŒ ط§ط¬ط¨ط§ط±غŒ ظˆ ع©ظ„غŒط¯غŒ
            'tenant_code'            => 'TENANT_' . strtoupper($this->faker->unique()->lexify('????')),
            'slug'                   => Str::slug($companyName), // ظپغŒظ„ط¯ ط§ط¬ط¨ط§ط±غŒ ط¬ط§ ط§ظپطھط§ط¯ظ‡

            // ظپغŒظ„ط¯ظ‡ط§غŒ طھع©ظ…غŒظ„غŒ ط¨ط± ط§ط³ط§ط³ ط³ط§ط®طھط§ط± ط¬ط¯ظˆظ„
            'legal_name'             => $companyName . ' Ltd',
            'tenant_type'            => 1,
            'primary_domain_enabled' => false,
            'domain_status'          => 1,
            'status'                 => 1,
        ];
    }
}