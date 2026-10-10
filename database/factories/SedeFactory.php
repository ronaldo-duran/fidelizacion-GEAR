<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Comercio;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sede>
 */
class SedeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comercio_id' => Comercio::factory(),
            'nombre' => 'Sede ' . fake()->city(),
            'direccion' => fake()->address(),
            'activa' => true,
        ];
    }
}
