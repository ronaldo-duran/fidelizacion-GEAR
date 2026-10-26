<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Comercio;
use App\Models\Sede;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siembra los escenarios de `tests/Fixtures/tablero-escenarios.json` y guarda
 * los ids reales de los comercios y las sedes del fixture.
 */
final class TableroTestData
{
    /** @var array<string, int> */
    public array $comercios = [];

    /** @var array<string, int> */
    public array $sedes = [];

    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/tablero-escenarios.json'), true);

        return is_array($fixture) ? $fixture : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fixtureCase(string $criterion): array
    {
        foreach (self::fixture()['cases'] as $case) {
            if ($case['_e10_01_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    }

    /**
     * @param  array<string, mixed>  $case
     */
    public static function seed(array $case): self
    {
        $data = app(self::class);
        $clientes = [];

        foreach ($case['comercios'] ?? [] as $comercio) {
            $creado = Comercio::factory()->create([
                'razon_social' => $comercio['nombre'],
                'activo' => $comercio['activo'],
            ]);
            $data->comercios[$comercio['id']] = $creado->id;

            foreach ($comercio['sedes'] as $sede) {
                $data->sedes[$sede] = Sede::factory()->create(['comercio_id' => $creado->id, 'nombre' => $sede])->id;
            }
        }

        foreach ($case['clientes'] ?? [] as $i => $cliente) {
            $clientes[$cliente['id']] = DB::table('clientes')->insertGetId([
                'tipo_documento' => 'CC',
                'documento' => (string) (70000000 + $i),
                'nivel_por_puntos' => $cliente['nivel_por_puntos'],
                'nivel_por_membresia' => $cliente['nivel_por_membresia'] ?? null,
                'nivel_por_evento' => $cliente['nivel_por_evento'] ?? null,
                'nivel_efectivo' => $cliente['nivel_efectivo'],
                'created_at' => CarbonImmutable::parse($cliente['created_at'] . ' 10:00:00'),
                'updated_at' => CarbonImmutable::parse($cliente['created_at'] . ' 10:00:00'),
            ]);
        }

        foreach ($case['transacciones'] ?? [] as $i => $transaccion) {
            $fecha = CarbonImmutable::parse($transaccion['created_at'] . ' 12:00:00');
            DB::table('transacciones')->insert([
                'cliente_id' => $transaccion['cliente'] === null ? null : $clientes[$transaccion['cliente']],
                'tipo_documento_cliente' => 'CC',
                'documento_cliente' => (string) (80000000 + $i),
                'comercio_id' => $data->comercios[$transaccion['comercio']],
                'sede_id' => $data->sedes[$transaccion['sede']],
                'monto' => 50000,
                'referencia_externa' => "tablero-{$i}",
                'canal' => 'cajero',
                'estado' => $transaccion['estado'],
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);
        }

        return $data;
    }
}
