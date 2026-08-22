<?php

namespace Database\Factories;

use App\Modules\IdentityCore\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'user_id'    => Str::uuid()->toString(),
            'first_name' => $this->faker->firstName,
            'last_name'  => $this->faker->lastName,
            'mobile'     => $this->faker->numerify('09#########'),
            'email'      => $this->faker->unique()->safeEmail,
            'user_kind'  => 1,
            'status'     => 1,
        ];
    }
}