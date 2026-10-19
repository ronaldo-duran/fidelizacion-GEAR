<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Compras\RegisterCompra;
use App\Actions\Puntos\RecordAcumulacion;
use App\Compras\NuevaCompra;
use App\Compras\RegistroCompra;
use App\Enums\CanalCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\TipoDocumento;
use App\Enums\TipoMovimiento;
use App\Enums\TipoRegla;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Models\Sede;
use App\Models\Transaccion;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Test data for the purchase registration (E4-01). The fixture names
 * merchants and branches by made-up ids; `seed()` creates them and keeps the
 * map to the real ones.
 */
final class ComprasTestData
{
    /** @var array<int, int> */
    public array $comercios = [];

    /** @var array<int, int> */
    public array $sedes = [];

    /** @var array<string, Regla> keyed by codigo */
    public array $reglas = [];

    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $path = __DIR__ . '/../Fixtures/compras-escenarios.json';

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fixtureCase(string $criterion): array
    {
        foreach (self::fixture()['cases'] as $case) {
            if ($case['_e4_01_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    }

    /**
     * Seeds the case and registers its purchase.
     *
     * @param  array<string, mixed>  $case
     * @return array{0: self, 1: Cliente|null, 2: RegistroCompra}
     */
    public static function register(array $case, User $user): array
    {
        $data = self::seed($case['rule_override'] ?? null);
        $cliente = ($case['cliente'] ?? null) === null ? null : $data->cliente($case['cliente']);
        $registro = app(RegisterCompra::class)($user, $data->compra($case['purchase'], $case['cliente'] ?? null));

        return [$data, $cliente, $registro];
    }

    /**
     * @param  list<array<string, mixed>>  $expected
     * @return list<array<string, mixed>>
     */
    public static function expectedMovimientos(array $expected): array
    {
        return array_map(static fn (array $movimiento): array => [
            'tipo' => TipoMovimiento::from($movimiento['tipo']),
            'puntos' => $movimiento['puntos'],
            'regla' => "{$movimiento['regla_codigo']} v{$movimiento['regla_version']}",
        ], $expected);
    }

    /**
     * The movements of a purchase, in the same shape as expectedMovimientos().
     *
     * @return list<array<string, mixed>>
     */
    public static function movimientosOf(Transaccion $transaccion): array
    {
        return array_values(MovimientoPuntos::query()->where('transaccion_id', $transaccion->id)->oldest('id')->get()
            ->map(static fn (MovimientoPuntos $movimiento): array => [
                'tipo' => $movimiento->tipo,
                'puntos' => $movimiento->puntos,
                'regla' => Regla::query()->findOrFail($movimiento->regla_id)->codigo . " v{$movimiento->regla_version}",
            ])->all());
    }

    /**
     * Merchants, branches, the accrual and bonus rules (with an optional cap
     * override) and the niveles rule.
     *
     * @param  array<string, mixed>|null  $ruleOverride
     */
    public static function seed(?array $ruleOverride = null): self
    {
        $data = app(self::class);
        $fixture = self::fixture();

        foreach ($fixture['comercios'] as $comercio) {
            $categoria = Categoria::query()->firstOrCreate(
                ['slug' => $comercio['categoria_slug']],
                ['nombre' => ucfirst($comercio['categoria_slug'])],
            );
            $creado = Comercio::factory()->create(['categoria_id' => $categoria->id, 'activo' => $comercio['activo']]);
            $data->comercios[$comercio['id']] = $creado->id;

            foreach ($comercio['sedes'] as $sede) {
                $data->sedes[$sede['id']] = Sede::factory()->create([
                    'comercio_id' => $creado->id,
                    'activa' => $sede['activa'],
                ])->id;
            }
        }

        foreach ($fixture['rules'] as $rule) {
            $definicion = $rule['definicion'];

            if ($ruleOverride !== null && $ruleOverride['codigo'] === $rule['codigo']) {
                $definicion['topes'] = $ruleOverride['topes'];
            }

            $data->reglas[$rule['codigo']] = Regla::query()->create([
                'codigo' => $rule['codigo'],
                'tipo' => TipoRegla::from($rule['tipo']),
                'version' => $rule['version'],
                'vigente_desde' => NivelesTestData::day('hoy')->subYear(),
                'activa' => true,
                'definicion' => $definicion,
            ]);
        }

        NivelesTestData::nivelesRegla();

        return $data;
    }

    /**
     * A registered customer. A starting balance enters through the ledger,
     * from a purchase two months ago, or today when the case says so, so the
     * caps see the real history.
     *
     * @param  array<string, mixed>  $cliente
     */
    public function cliente(array $cliente): Cliente
    {
        $creado = Cliente::query()->create([
            'tipo_documento' => TipoDocumento::from($cliente['tipo_documento'] ?? 'CC'),
            'documento' => $cliente['documento'],
        ]);

        $hoy = $cliente['puntos_acumulados_hoy'] ?? 0;
        $antes = ($cliente['saldo_puntos'] ?? 0) - $hoy;

        $this->history($creado, $antes, CarbonImmutable::now()->subMonths(2));
        $this->history($creado, $hoy, CarbonImmutable::now()->subHour());

        $creado->forceFill(['nivel_efectivo' => $cliente['nivel_efectivo'] ?? 'bronce'])->save();

        return $creado->refresh();
    }

    /**
     * @param  array<string, mixed>  $purchase
     * @param  array<string, mixed>|null  $cliente
     */
    public function compra(
        array $purchase,
        ?array $cliente = null,
        CanalCompra $canal = CanalCompra::Cajero,
    ): NuevaCompra {
        return new NuevaCompra(
            $this->comercios[$purchase['comercio_id']],
            isset($purchase['sede_id']) ? $this->sedes[$purchase['sede_id']] : null,
            TipoDocumento::from($purchase['tipo_documento_cliente'] ?? $cliente['tipo_documento'] ?? 'CC'),
            $purchase['documento_cliente'] ?? $cliente['documento'] ?? '0000000000',
            $purchase['monto'],
            $purchase['referencia_externa'] ?? 'ref-' . fake()->unique()->numerify('######'),
            $canal,
        );
    }

    /**
     * A pending purchase made on a past date.
     *
     * @param  array<string, mixed>  $pending
     */
    public function pendiente(array $pending): Transaccion
    {
        $fecha = NivelesTestData::day($pending['created_at']);

        return self::at($fecha, fn (): Transaccion => Transaccion::query()->create([
            'tipo_documento_cliente' => TipoDocumento::from($pending['tipo_documento_cliente'] ?? 'CC'),
            'documento_cliente' => $pending['documento_cliente'],
            'comercio_id' => $this->comercios[$pending['comercio_id']],
            'sede_id' => null,
            'monto' => $pending['monto'],
            'referencia_externa' => $pending['referencia_externa'],
            'canal' => CanalCompra::Api,
            'estado' => EstadoTransaccion::Pendiente,
        ]));
    }

    /**
     * Runs the callback with the clock at that instant.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function at(CarbonImmutable $instant, callable $callback): mixed
    {
        $now = CarbonImmutable::now();
        Carbon::setTestNow($instant);
        CarbonImmutable::setTestNow($instant);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($now);
            CarbonImmutable::setTestNow($now);
        }
    }

    private function history(Cliente $cliente, int $puntos, CarbonImmutable $at): void
    {
        if ($puntos <= 0) {
            return;
        }

        self::at($at, function () use ($cliente, $puntos, $at): void {
            $transaccion = Transaccion::factory()->create([
                'comercio_id' => reset($this->comercios),
                'cliente_id' => $cliente->id,
                'estado' => EstadoTransaccion::Acreditada,
            ]);
            app(RecordAcumulacion::class)(
                $cliente->id,
                $transaccion->comercio_id,
                $transaccion->id,
                $puntos,
                $this->reglas['acumulacion-base'],
                $at->addYear(),
            );
        });
    }
}
