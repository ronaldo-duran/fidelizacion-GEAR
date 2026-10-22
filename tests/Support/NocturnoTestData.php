<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\EstadoTransaccion;
use App\Enums\TipoMovimiento;
use App\Enums\TipoRegla;
use App\Jobs\Nocturno\ProcesosNocturnos;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Models\Transaccion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

/**
 * Test data for the nightly processes (E3-05).
 */
final class NocturnoTestData
{
    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function cases(): array
    {
        $path = __DIR__ . '/../Fixtures/caducidad-escenarios.json';
        $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $cases = [];

        foreach ($fixture['cases'] as $case) {
            $cases[$case['_e3_05_criterion']] = [$case];
        }

        return $cases;
    }

    /**
     * Replaces the `alertas` log channel with a spy.
     */
    public static function alertas(): MockInterface
    {
        $alertas = Mockery::spy(LoggerInterface::class);
        Log::shouldReceive('channel')->with('alertas')->andReturn($alertas);

        return $alertas;
    }

    /**
     * Runs tonight's processes synchronously, in order.
     */
    public static function runNight(): void
    {
        foreach (ProcesosNocturnos::for(CarbonImmutable::today()) as $job) {
            Bus::dispatchSync($job);
        }
    }

    /**
     * Two versions of the accrual rule. The fixture names them by
     * `regla_id` 1 and versions 1 and 2; the map gives their real ids.
     *
     * @return array<string, Regla> keyed by "regla_id:version" of the fixture
     */
    public static function reglas(): array
    {
        $reglas = [];

        foreach ([1, 2] as $version) {
            $reglas["1:{$version}"] = Regla::query()->create([
                'codigo' => 'acumulacion-base',
                'tipo' => TipoRegla::Acumulacion,
                'version' => $version,
                'vigente_desde' => CarbonImmutable::now()->subYears(3 - $version),
                'vigente_hasta' => $version === 1 ? CarbonImmutable::now()->subYear() : null,
                'activa' => true,
                'definicion' => [
                    'moneda_por_punto' => 1000,
                    'tasas_por_categoria' => [['categoria_slug' => 'restaurante', 'puntos_por_unidad' => 1]],
                    'vigencia_puntos_meses' => 12,
                ],
            ]);
        }

        return $reglas;
    }

    /**
     * Writes the fixture movements straight into the ledger, filling the
     * facts the fixture leaves out (merchant, purchase, user), and sets the
     * materialized balance as given.
     *
     * @param  array<string, mixed>  $case
     * @param  array<string, Regla>  $reglas
     */
    public static function seed(Cliente $cliente, array $case, array $reglas): void
    {
        $comercio = Comercio::factory()->create();
        $user = User::factory()->administradorDelGrupo()->create();
        $transaccion = 5000;
        $transacciones = [];

        foreach ($case['movimientos'] as $movimiento) {
            $regla = $movimiento['regla_id'] === null
                ? null
                : $reglas["{$movimiento['regla_id']}:{$movimiento['regla_version']}"];
            $tipo = TipoMovimiento::from($movimiento['tipo']);
            $porCompra = in_array($tipo, [TipoMovimiento::Acumulacion, TipoMovimiento::Reversa], true);
            $transaccionId = null;

            if ($porCompra) {
                $clave = $movimiento['transaccion_id'] ?? ++$transaccion;
                $transaccionId = $transacciones[$clave] ??= Transaccion::factory()->create([
                    'cliente_id' => $cliente->id,
                    'comercio_id' => $comercio->id,
                    'estado' => EstadoTransaccion::Acreditada,
                ])->id;
            }

            MovimientoPuntos::query()->create([
                'cliente_id' => $cliente->id,
                'comercio_id' => $porCompra ? $comercio->id : null,
                'transaccion_id' => $transaccionId,
                'tipo' => $tipo,
                'puntos' => $movimiento['puntos'],
                'regla_id' => $regla?->id,
                'regla_version' => $regla?->version,
                'vigencia_hasta' => isset($movimiento['vigencia_hasta'])
                    ? NivelesTestData::day($movimiento['vigencia_hasta'])
                    : null,
                'motivo' => $movimiento['motivo'] ?? null,
                'usuario_id' => isset($movimiento['motivo']) ? $user->id : null,
            ]);
        }

        $cliente->forceFill(['saldo_puntos' => $case['initial_saldo']])->save();
    }
}
