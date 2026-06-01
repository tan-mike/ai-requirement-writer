<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Persona>
 */
class PersonaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(),
            'name' => $this->faker->name(),
            'role' => $this->faker->jobTitle(),
            'system_prompt' => $this->faker->paragraph(),
            'cost_multiplier' => 1.0,
            'is_global' => true,
            'is_active' => true,
        ];
    }
}
