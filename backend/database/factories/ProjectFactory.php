<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['webapp', 'mobile', 'api', 'data', 'custom']),
            'status' => 'draft',
        ];
    }
}
