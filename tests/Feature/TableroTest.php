<?php

declare(strict_types=1);

use App\Tablero\ActividadDeComercios;
use App\Tablero\AlcanceTablero;
use App\Tablero\ClientesPorNivel;
use App\Tablero\FormatoTablero;
use App\Tablero\KpisDelPeriodo;
use App\Tablero\Periodo;
use Carbon\CarbonImmutable;
use Tests\Support\TableroTestData;

beforeEach(function (): void {
    $this->fixture = TableroTestData::fixture();
});

it('keeps the dashboard settings in config equal to the fixture', function (): void {
    $settings = $this->fixture['settings'];

    expect(config('tablero.selectable_months'))->toBe($settings['selectable_months']['value'])
        ->and(config('tablero.inactivity_days'))->toBe($settings['inactivity_days']['value'])
        ->and(config('tablero.comercios_before_more'))->toBe($settings['comercios_before_more']['value']);
});

it('formats deltas, money, the period and the inactive label as in the fixture', function (): void {
    $formats = $this->fixture['formats'];

    foreach ($formats['delta'] as $delta) {
        expect(FormatoTablero::variacion($delta['current'], $delta['previous']))->toBe($delta['expected']);
    }

    foreach ($formats['money'] as $money) {
        expect(FormatoTablero::dinero($money['value']))->toBe($money['expected']);
    }

    expect(Periodo::desde($formats['period_label']['period'])->etiqueta())->toBe($formats['period_label']['expected'])
        ->and(FormatoTablero::sinRegistros($formats['inactive_label']['days']))
        ->toBe($formats['inactive_label']['expected']);
});

it('computes the group dashboard for a period', function (): void {
    $case = TableroTestData::fixtureCase('group dashboard for a period');
    $data = TableroTestData::seed($case);
    $this->travelTo(CarbonImmutable::parse($case['now']));
    $periodo = Periodo::desde($case['period']);
    $expected = $case['expected'];

    $kpis = app(KpisDelPeriodo::class)($periodo, AlcanceTablero::grupo());
    $niveles = app(ClientesPorNivel::class)($periodo, AlcanceTablero::grupo());
    $actividad = app(ActividadDeComercios::class)($periodo, AlcanceTablero::grupo());

    expect($kpis['clientes'])->toBe($expected['kpis']['clientes_inscritos'])
        ->and($kpis['clientes_nuevos'])->toBe($expected['kpis']['clientes_nuevos'])
        ->and($kpis['transacciones'])->toBe($expected['kpis']['transacciones'])
        ->and(FormatoTablero::variacion($kpis['transacciones'], $kpis['transacciones_anterior']))
        ->toBe($expected['kpis']['transacciones_delta'])
        ->and($kpis['beneficios_entregados'])->toBe($expected['kpis']['beneficios_entregados'])
        ->and($kpis['beneficios_por_100_compras'])->toBe($expected['kpis']['beneficios_por_100_compras'])
        ->and($kpis['valor_beneficios'])->toBe($expected['kpis']['valor_beneficios'])
        ->and($niveles['niveles'])->toBe($expected['niveles'])
        ->and($niveles['diamante_origen'])->toBe($expected['diamante_origen'])
        ->and(array_map(static fn (array $fila): array => [
            'id' => $fila['id'],
            'transacciones' => $fila['transacciones'],
            'delta' => FormatoTablero::variacion($fila['transacciones'], $fila['transacciones_anterior']),
            'inactivo' => $fila['inactivo'],
        ], $actividad))
        ->toBe(array_map(static fn (array $fila): array => [
            'id' => $data->comercios[$fila['comercio']],
            'transacciones' => $fila['transacciones'],
            'delta' => $fila['delta'],
            'inactivo' => $fila['inactivo'],
        ], $expected['actividad']))
        ->and(end($actividad)['dias_sin_registros'])->toBe(end($expected['actividad'])['dias_sin_registros']);
});

it('filters the dashboard of a merchant admin to its merchant and lists its branches', function (): void {
    $case = TableroTestData::fixtureCase('merchant dashboard is filtered to its merchant');
    $data = TableroTestData::seed(TableroTestData::fixtureCase($case['uses_data_of']));
    $this->travelTo(CarbonImmutable::parse($case['now']));
    $periodo = Periodo::desde($case['period']);
    $alcance = AlcanceTablero::comercio($data->comercios[$case['viewer']['comercio']]);
    $expected = $case['expected'];

    $kpis = app(KpisDelPeriodo::class)($periodo, $alcance);
    $actividad = app(ActividadDeComercios::class)($periodo, $alcance);

    expect($kpis['clientes'])->toBe($expected['kpis']['clientes_que_compraron'])
        ->and($kpis['transacciones'])->toBe($expected['kpis']['transacciones'])
        ->and(FormatoTablero::variacion($kpis['transacciones'], $kpis['transacciones_anterior']))
        ->toBe($expected['kpis']['transacciones_delta'])
        ->and($kpis['beneficios_entregados'])->toBe($expected['kpis']['beneficios_entregados'])
        ->and($kpis['valor_beneficios'])->toBe($expected['kpis']['valor_beneficios'])
        ->and(array_map(static fn (array $fila): array => [
            'id' => $fila['id'],
            'transacciones' => $fila['transacciones'],
            'delta' => FormatoTablero::variacion($fila['transacciones'], $fila['transacciones_anterior']),
        ], $actividad))
        ->toBe(array_map(static fn (array $fila): array => [
            'id' => $data->sedes[$fila['sede']],
            'transacciones' => $fila['transacciones'],
            'delta' => $fila['delta'],
        ], $expected['actividad_por_sede']));
});

it('shows zeros for an empty program', function (): void {
    $case = TableroTestData::fixtureCase('an empty program shows zeros');
    $this->travelTo(CarbonImmutable::parse($case['now']));
    $periodo = Periodo::desde($case['period']);
    $expected = $case['expected'];

    $kpis = app(KpisDelPeriodo::class)($periodo, AlcanceTablero::grupo());

    expect($kpis['clientes'])->toBe($expected['kpis']['clientes_inscritos'])
        ->and($kpis['transacciones'])->toBe($expected['kpis']['transacciones'])
        ->and(FormatoTablero::variacion($kpis['transacciones'], $kpis['transacciones_anterior']))
        ->toBe($expected['kpis']['transacciones_delta'])
        ->and($kpis['beneficios_por_100_compras'])->toBe($expected['kpis']['beneficios_por_100_compras'])
        ->and(app(ActividadDeComercios::class)($periodo, AlcanceTablero::grupo()))->toBe($expected['actividad']);
});

it('shows the first merchants of the order and every inactive one before "Ver los N"', function (): void {
    $filas = array_map(
        static fn (int $i): array => ['id' => $i, 'inactivo' => $i === 8],
        range(1, 9),
    );

    expect(array_column(ActividadDeComercios::visibles($filas), 'id'))
        ->toBe([...range(1, $this->fixture['settings']['comercios_before_more']['value']), 8]);
});

it('offers the selectable months from the current one backwards', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00'));
    $opciones = Periodo::opciones();

    expect($opciones)->toHaveCount($this->fixture['settings']['selectable_months']['value'])
        ->and(array_key_first($opciones))->toBe('2026-10')
        ->and($opciones['2026-10'])->toBe('Octubre 2026 · vs. septiembre')
        ->and(array_key_last($opciones))->toBe('2025-11');
});
