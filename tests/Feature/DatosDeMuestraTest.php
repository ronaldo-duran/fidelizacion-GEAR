<?php

declare(strict_types=1);

use App\Enums\EstadoTransaccion;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Models\Transaccion;
use App\Tablero\ActividadDeComercios;
use App\Tablero\AlcanceTablero;
use App\Tablero\KpisDelPeriodo;
use App\Tablero\Periodo;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

it('does not seed sample data unless the variable is on', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 17:00:00'));
    config(['muestra.sembrar' => false]);

    $this->seed(DatabaseSeeder::class);

    expect(Cliente::query()->count())->toBe(0)
        ->and(Transaccion::query()->count())->toBe(0)
        ->and(Regla::query()->where('codigo', 'acumulacion-base')->sole()->vigente_desde->isSameDay(now()))->toBeTrue();
});

it('seeds a month of history against the previous one, with every case the dashboard shows', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-26 17:00:00'));
    config(['muestra.sembrar' => true]);

    $this->seed(DatabaseSeeder::class);

    $kpis = app(KpisDelPeriodo::class)(Periodo::actual(), AlcanceTablero::grupo());
    $actividad = collect(app(ActividadDeComercios::class)(Periodo::actual(), AlcanceTablero::grupo()))
        ->keyBy('nombre');
    $niveles = Cliente::query()->get()->countBy(fn (Cliente $cliente): string => $cliente->nivel_efectivo->value);

    expect(Regla::query()->where('codigo', 'acumulacion-base')->sole()->vigente_desde->toDateString())
        ->toBe('2026-09-01')
        ->and(Cliente::query()->count())->toBe(12)
        ->and($kpis['transacciones'])->toBeGreaterThan($kpis['transacciones_anterior'])
        ->and($kpis['transacciones_anterior'])->toBeGreaterThan(0)
        ->and($kpis['clientes_nuevos'])->toBe(3)
        ->and($niveles->keys()->sort()->values()->all())->toBe(['bronce', 'diamante', 'oro', 'plata'])
        ->and(Transaccion::query()->where('estado', EstadoTransaccion::Anulada->value)->count())->toBe(1)
        ->and(Transaccion::query()->where('estado', EstadoTransaccion::Pendiente->value)->count())->toBe(2)
        ->and($actividad['Colegio Semillitas del Futuro']['inactivo'])->toBeTrue()
        ->and($actividad['Heladería Polo Norte']['inactivo'])->toBeTrue()
        ->and($actividad['Cervecería BBC']['inactivo'])->toBeFalse();

    // El libro queda coherente: cada saldo es la suma de sus movimientos.
    foreach (Cliente::query()->get() as $cliente) {
        expect($cliente->saldo_puntos)
            ->toBe((int) MovimientoPuntos::query()->where('cliente_id', $cliente->id)->sum('puntos'));
    }
});

it('never dates a sample purchase in the future, even early in the month', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00:00'));
    config(['muestra.sembrar' => true]);

    $this->seed(DatabaseSeeder::class);

    expect(Transaccion::query()->max('created_at'))->toBeLessThanOrEqual(now()->toDateTimeString())
        ->and(Transaccion::query()->where('created_at', '>=', now()->startOfMonth())->count())->toBeGreaterThan(0)
        ->and(Comercio::query()->where('nit', '800200200')->sole()->sedes()->count())->toBe(2);
});
