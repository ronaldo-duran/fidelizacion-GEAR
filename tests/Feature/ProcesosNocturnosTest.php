<?php

declare(strict_types=1);

use App\Actions\Nocturno\ExpireLotes;
use App\Enums\MotivoCambioNivel;
use App\Enums\TipoMovimiento;
use App\Jobs\Nocturno\ProcesosNocturnos;
use App\Models\AvisoDescenso;
use App\Models\CambioNivel;
use App\Models\MovimientoPuntos;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\Support\NivelesTestData;
use Tests\Support\NocturnoTestData;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 01:30:00'));
    NivelesTestData::nivelesRegla();
    $this->reglas = NocturnoTestData::reglas();
});

it('runs each fixture scenario through the nightly processes', function (array $case): void {
    $alertas = NocturnoTestData::alertas();
    $cliente = NivelesTestData::cliente($case['sources'] ?? []);
    NocturnoTestData::seed($cliente, $case, $this->reglas);
    $antes = MovimientoPuntos::query()->max('id');

    foreach ($case['existing_avisos'] ?? [] as $aviso) {
        AvisoDescenso::query()->create([
            'cliente_id' => $cliente->id,
            'fecha_vencimiento' => NivelesTestData::day($aviso['fecha_vencimiento']),
            'dias_antes' => $aviso['dias_antes'],
            'puntos_que_vencen' => 200,
            'nivel_actual' => 'oro',
            'nivel_resultante' => 'plata',
        ]);
    }

    NocturnoTestData::runNight();
    $cliente->refresh();

    if (array_key_exists('expected_caducidades', $case)) {
        $caducidades = MovimientoPuntos::query()->where('id', '>', $antes)->oldest('id')->get();

        expect($caducidades->map(static fn (MovimientoPuntos $m): array => [
            'tipo' => $m->tipo,
            'puntos' => $m->puntos,
            'regla_id' => $m->regla_id,
            'regla_version' => $m->regla_version,
            'has_motivo' => $m->motivo !== null,
        ])->all())->toBe(array_map(fn (array $expected): array => [
            'tipo' => TipoMovimiento::Caducidad,
            'puntos' => $expected['puntos'],
            'regla_id' => $expected['regla_id'] === null
                ? null
                : $this->reglas["{$expected['regla_id']}:{$expected['regla_version']}"]->id,
            'regla_version' => $expected['regla_version'],
            'has_motivo' => $expected['has_motivo'] ?? false,
        ], $case['expected_caducidades']), $case['_description']);
    }

    if (isset($case['expected_saldo'])) {
        expect($cliente->saldo_puntos)->toBe($case['expected_saldo']);
    }

    if (isset($case['expected_nivel_efectivo'])) {
        expect($cliente->nivel_efectivo->value)->toBe($case['expected_nivel_efectivo']);
    }

    if (($case['records_level_change'] ?? false) === true) {
        expect(CambioNivel::query()->where('cliente_id', $cliente->id)->sole()->motivo)
            ->toBe(MotivoCambioNivel::DescensoNocturno);
    }

    if (array_key_exists('expected_avisos', $case)) {
        $avisos = AvisoDescenso::query()->where('cliente_id', $cliente->id)->get();

        expect($avisos)->toHaveCount(count($case['expected_avisos']) + count($case['existing_avisos'] ?? []));

        foreach ($case['expected_avisos'] as $expected) {
            $aviso = $avisos->firstWhere('dias_antes', $expected['dias_antes']);

            expect($aviso?->fecha_vencimiento->toDateString())
                ->toBe(NivelesTestData::day($expected['fecha_vencimiento'])->toDateString())
                ->and($aviso?->puntos_que_vencen)->toBe($expected['puntos_que_vencen'])
                ->and($aviso?->nivel_actual->value)->toBe($expected['nivel_actual'])
                ->and($aviso?->nivel_resultante->value)->toBe($expected['nivel_resultante']);
        }
    }

    if (($case['expected_alert'] ?? false) === true) {
        $alertas->shouldHaveReceived('warning')->once();
    } else {
        $alertas->shouldNotHaveReceived('warning');
        $alertas->shouldNotHaveReceived('error');
    }
})->with(NocturnoTestData::cases());

it('is idempotent: a second run the same night changes nothing', function (): void {
    $case = NocturnoTestData::cases()['notice when the expiring points lower the level'][0];
    $case['movimientos'][] = [
        'tipo' => 'acumulacion',
        'puntos' => 100,
        'regla_id' => 1,
        'regla_version' => 1,
        'vigencia_hasta' => 'hoy-1d',
    ];
    $case['initial_saldo'] += 100;
    $cliente = NivelesTestData::cliente($case['sources']);
    NocturnoTestData::seed($cliente, $case, $this->reglas);

    NocturnoTestData::runNight();
    $movimientos = MovimientoPuntos::query()->count();
    $avisos = AvisoDescenso::query()->count();
    $saldo = $cliente->fresh()?->saldo_puntos;

    NocturnoTestData::runNight();

    expect(MovimientoPuntos::query()->count())->toBe($movimientos)
        ->and(AvisoDescenso::query()->count())->toBe($avisos)
        ->and($cliente->fresh()?->saldo_puntos)->toBe($saldo)
        ->and($avisos)->toBe(1);
});

it('keeps going when one customer fails and logs it as an alert', function (): void {
    $alertas = NocturnoTestData::alertas();
    $this->mock(ExpireLotes::class)->shouldReceive('__invoke')->andThrow(new RuntimeException('boom'));
    $case = NocturnoTestData::cases()['an expired lot leaves through a caducidad movement with its rule'][0];
    NocturnoTestData::seed(NivelesTestData::cliente([]), $case, $this->reglas);

    NocturnoTestData::runNight();

    $alertas->shouldHaveReceived('error')->once();
});

it('schedules the nightly processes in Bogota time, without overlapping and on one server', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(static fn (Event $event): bool => $event->description === 'procesos-nocturnos');

    expect($event)->not->toBeNull()
        ->and($event?->expression)->toBe('30 1 * * *')
        ->and($event?->timezone)->toBe('America/Bogota')
        ->and($event?->withoutOverlapping)->toBeTrue()
        ->and($event?->onOneServer)->toBeTrue();
});

it('chains the nightly processes in order on the nightly queue', function (): void {
    $jobs = ProcesosNocturnos::for(CarbonImmutable::parse('2026-10-17'));

    expect(array_map(static fn (object $job): string => class_basename($job), $jobs))
        ->toBe([
            'ExpirePuntos',
            'ReevaluateNiveles',
            'RegisterAvisosDescenso',
            'VerifySaldos',
            'PurgeCodigosVerificacion',
            'PurgeRegistrosPendientes',
        ])
        ->and(array_map(static fn (object $job): ?string => $job->queue, $jobs))
        ->toBe(array_fill(0, 6, 'nocturno'));
});

it('reruns a night by hand with the console command', function (): void {
    $case = NocturnoTestData::cases()['a missed night is recovered by the next run'][0];
    $cliente = NivelesTestData::cliente([]);
    NocturnoTestData::seed($cliente, $case, $this->reglas);

    $this->artisan('nocturno:correr', ['fecha' => '2026-10-17'])->assertSuccessful();

    expect($cliente->fresh()?->saldo_puntos)->toBe(0);
});
