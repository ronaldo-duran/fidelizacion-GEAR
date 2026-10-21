<?php

declare(strict_types=1);

use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Actions\Niveles\ReevaluateNivelEfectivo;
use App\Actions\Puntos\RecordAcumulacion;
use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use App\Models\CambioNivel;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\EventoCalificado;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Support\LedgerTestData;
use Tests\Support\NivelesTestData;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 01:30:00'));
});

it('keeps the event rules in config equal to the fixture', function (): void {
    $fixture = NivelesTestData::fixture()['event_rules'];
    unset($fixture['_note'], $fixture['matricula_colegio']['_note']);

    expect(config('niveles.event_rules'))->toBe($fixture);
});

it('computes the effective level of each fixture scenario', function (array $case): void {
    NivelesTestData::nivelesRegla($case['proteccion_meses'] ?? 0);
    $cliente = NivelesTestData::cliente($case['sources']);
    $antes = $cliente->nivel_efectivo;

    foreach ($case['eventos'] ?? [] as $evento) {
        NivelesTestData::evento($cliente, $evento);
    }

    $result = ($case['action'] ?? null) === 'nightly_evaluation'
        ? app(ReevaluateNivelEfectivo::class)($cliente->id)
        : app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoEvento);
    $result = $result->fresh() ?? $result;

    expect($result->nivel_efectivo->value)->toBe($case['expected_nivel_efectivo'], $case['_description']);

    if (array_key_exists('expected_nivel_por_evento', $case)) {
        expect($result->nivel_por_evento?->value)->toBe($case['expected_nivel_por_evento']);
    }

    if (isset($case['expected_evento_vigente_hasta'])) {
        expect($result->evento_vigente_hasta?->toDateString())
            ->toBe(NivelesTestData::day($case['expected_evento_vigente_hasta'])->toDateString());
    }

    if (($case['records_change'] ?? false) === true) {
        $cambio = CambioNivel::query()->where('cliente_id', $cliente->id)->sole();

        expect($cambio->nivel_anterior)->toBe($antes)
            ->and($cambio->nivel_nuevo->value)->toBe($case['expected_nivel_efectivo'])
            ->and($cambio->motivo)->toBe(MotivoCambioNivel::DescensoNocturno)
            ->and($cambio->created_at->toDateTimeString())->toBe('2026-10-17 01:30:00')
            ->and($result->nivel_efectivo_desde?->toDateTimeString())->toBe('2026-10-17 01:30:00');
    }
})->with(NivelesTestData::sourceCases());

it('promotes in the same request that records the purchase', function (): void {
    $case = NivelesTestData::fixtureCase('synchronous promotion in the same request');
    NivelesTestData::nivelesRegla();
    $cliente = NivelesTestData::cliente([
        'nivel_por_puntos' => $case['nivel_before'],
        'saldo_puntos' => $case['saldo_before'],
    ]);
    $regla = LedgerTestData::acumulacionRegla();
    $comercio = Comercio::factory()->create();

    DB::transaction(function () use ($cliente, $comercio, $regla, $case): void {
        app(RecordAcumulacion::class)(
            $cliente->id,
            $comercio->id,
            9001,
            $case['purchase_puntos'],
            $regla,
            CarbonImmutable::now()->addYear(),
        );
        app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoCompra);
    });

    $cliente->refresh();

    expect($cliente->saldo_puntos)->toBe($case['saldo_after'])
        ->and($cliente->nivel_efectivo->value)->toBe($case['expected_nivel_efectivo'])
        ->and(CambioNivel::query()->where('cliente_id', $cliente->id)->sole()->motivo)
        ->toBe(MotivoCambioNivel::AscensoCompra);
});

it('never lowers the level outside the nightly evaluation', function (): void {
    NivelesTestData::nivelesRegla();
    $cliente = NivelesTestData::cliente([
        'nivel_por_puntos' => 'oro',
        'nivel_efectivo' => 'oro',
        'saldo_puntos' => 100,
    ]);

    $result = app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoCompra);

    expect($result->nivel_efectivo)->toBe(Nivel::Oro)
        ->and($result->nivel_por_puntos)->toBe(Nivel::Bronce)
        ->and(CambioNivel::query()->count())->toBe(0);
});

it('refuses a promotion with a demotion reason', function (): void {
    $cliente = NivelesTestData::cliente([]);

    expect(fn (): Cliente => app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::DescensoNocturno))
        ->toThrow(LogicException::class);
});

it('refuses the nightly evaluation outside the console', function (): void {
    $cliente = NivelesTestData::cliente([]);
    $app = app();
    (fn () => $this->isRunningInConsole = false)->call($app);

    try {
        expect(fn (): Cliente => app(ReevaluateNivelEfectivo::class)($cliente->id))
            ->toThrow(LogicException::class, 'nightly evaluation');
    } finally {
        (fn () => $this->isRunningInConsole = true)->call($app);
    }
});

it('starts the protection on the first night below the level', function (): void {
    NivelesTestData::nivelesRegla(3);
    $cliente = NivelesTestData::cliente([
        'nivel_por_puntos' => 'oro',
        'nivel_efectivo' => 'oro',
        'saldo_puntos' => 1200,
    ]);

    $result = app(ReevaluateNivelEfectivo::class)($cliente->id);

    expect($result->nivel_efectivo)->toBe(Nivel::Oro)
        ->and($result->proteccion_hasta?->toDateString())->toBe('2027-01-17')
        ->and(CambioNivel::query()->count())->toBe(0);
});

it('clears the protection when the sources recover', function (): void {
    NivelesTestData::nivelesRegla(3);
    $cliente = NivelesTestData::cliente([
        'nivel_por_puntos' => 'oro',
        'nivel_efectivo' => 'oro',
        'saldo_puntos' => 1600,
        'proteccion_hasta' => 'hoy+20d',
    ]);

    expect(app(ReevaluateNivelEfectivo::class)($cliente->id)->proteccion_hasta)->toBeNull();
});

it('counts the last day of an event as valid', function (): void {
    NivelesTestData::nivelesRegla();
    $cliente = NivelesTestData::cliente([]);
    NivelesTestData::evento($cliente, ['tipo' => 'compra_inmueble', 'vigente_hasta' => 'hoy']);

    expect(app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoEvento)->nivel_efectivo)
        ->toBe(Nivel::Diamante);
});

it('locks the customer row before changing the level', function (): void {
    NivelesTestData::nivelesRegla();
    $cliente = NivelesTestData::cliente([]);
    $locks = [];
    DB::listen(static function (QueryExecuted $query) use (&$locks): void {
        if (str_contains($query->sql, 'clientes') && str_contains(strtolower($query->sql), 'for update')) {
            $locks[] = $query->sql;
        }
    });

    app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoCompra);

    expect($locks)->not->toBe([]);
})->skip(fn (): bool => DB::getDriverName() === 'sqlsrv', 'SQL Server locks with table hints, not FOR UPDATE.');

it('never updates nor deletes a level change', function (): void {
    $cliente = NivelesTestData::cliente([]);
    $cambio = CambioNivel::query()->create([
        'cliente_id' => $cliente->id,
        'nivel_anterior' => Nivel::Bronce,
        'nivel_nuevo' => Nivel::Plata,
        'motivo' => MotivoCambioNivel::AscensoCompra,
    ]);

    expect(fn (): bool => $cambio->update(['nivel_nuevo' => Nivel::Oro]))->toThrow(LogicException::class)
        ->and(fn (): ?bool => $cambio->delete())->toThrow(LogicException::class);
});

it('only lets a qualifying event be closed once, never edited nor deleted', function (): void {
    $cliente = NivelesTestData::cliente([]);
    $evento = NivelesTestData::evento($cliente, ['tipo' => 'matricula_colegio', 'vigente_hasta' => 'hoy+5m']);

    expect(fn (): bool => $evento->update(['tipo' => 'compra_inmueble']))->toThrow(LogicException::class)
        ->and(fn (): ?bool => $evento->delete())->toThrow(LogicException::class);

    $evento = $evento->fresh() ?? $evento;
    $evento->forceFill(['vigente_hasta' => CarbonImmutable::today(), 'motivo_cierre' => 'Retiro'])->save();

    expect(fn (): bool => $evento->forceFill(['vigente_hasta' => CarbonImmutable::today()->addYear()])->save())
        ->toThrow(LogicException::class)
        ->and(EventoCalificado::query()->find($evento->id)?->vigente_hasta->toDateString())->toBe('2026-10-17');
});
