<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CanalCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\TipoDocumento;
use App\Models\Comercio;
use App\Models\Transaccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaccion>
 */
class TransaccionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_documento_cliente' => TipoDocumento::CC,
            'documento_cliente' => (string) fake()->unique()->numerify('##########'),
            'comercio_id' => Comercio::factory(),
            'monto' => 10000,
            'referencia_externa' => fake()->unique()->bothify('ref-########'),
            'canal' => CanalCompra::Cajero,
            'estado' => EstadoTransaccion::Pendiente,
        ];
    }
}
