<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Comercio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comercio>
 */
class ComercioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'razon_social' => fake()->company(),
            'nit' => (string) fake()->unique()->numberBetween(800_000_000, 999_999_999),
            'categoria_id' => Categoria::factory(),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
