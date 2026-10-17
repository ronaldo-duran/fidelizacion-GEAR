<?php

declare(strict_types=1);

use App\Reglas\CompraData;
use App\Reglas\CondicionesEvaluator;
use Carbon\CarbonImmutable;
use Tests\Support\SimuladorTestData;

it('evaluates each whitelisted field and operator', function (
    string $campo,
    string $operador,
    mixed $valor,
    bool $expected,
): void {
    $condicion = ['campo' => $campo, 'operador' => $operador, 'valor' => $valor];

    $holds = app(CondicionesEvaluator::class)->holdAll([$condicion], SimuladorTestData::saturdayPurchase());

    expect($holds)->toBe($expected);
})->with([
    'monto equals' => ['monto', 'igual', 60_000, true],
    'monto differs' => ['monto', 'distinto', 60_000, false],
    'monto greater' => ['monto', 'mayor', 59_999, true],
    'monto greater at the edge' => ['monto', 'mayor', 60_000, false],
    'monto greater or equal at the edge' => ['monto', 'mayor_igual', 60_000, true],
    'monto less' => ['monto', 'menor', 60_000, false],
    'monto less or equal' => ['monto', 'menor_igual', 60_000, true],
    'category equals' => ['categoria_slug', 'igual', 'restaurante', true],
    'category in list' => ['categoria_slug', 'en', ['heladeria', 'restaurante'], true],
    'category not in list' => ['categoria_slug', 'en', ['heladeria'], false],
    'comercio as text from the JSON editor' => ['comercio_id', 'igual', '3', true],
    'sede equals' => ['sede_id', 'igual', 7, true],
    'weekday saturday' => ['dia_semana', 'en', [6, 7], true],
    'hour' => ['hora', 'mayor_igual', 18, true],
    'ordering against text never holds' => ['monto', 'mayor', '1', false],
    'unknown operator never holds' => ['monto', 'contiene', 1, false],
    'unknown field never holds' => ['cliente_id', 'igual', 1, false],
]);

it('requires every condition to hold', function (): void {
    $condiciones = [
        ['campo' => 'categoria_slug', 'operador' => 'igual', 'valor' => 'restaurante'],
        ['campo' => 'monto', 'operador' => 'mayor_igual', 'valor' => 100_000],
    ];

    expect(app(CondicionesEvaluator::class)->holdAll($condiciones, SimuladorTestData::saturdayPurchase()))->toBeFalse();
});

it('never matches a sede when the purchase has none', function (): void {
    $igual = [['campo' => 'sede_id', 'operador' => 'igual', 'valor' => 7]];
    $distinto = [['campo' => 'sede_id', 'operador' => 'distinto', 'valor' => 7]];
    $evaluator = app(CondicionesEvaluator::class);

    expect($evaluator->holdAll($igual, SimuladorTestData::saturdayPurchase(null)))->toBeFalse()
        ->and($evaluator->holdAll($distinto, SimuladorTestData::saturdayPurchase(null)))->toBeTrue();
});

it('reads the weekday and hour in Bogota', function (): void {
    // 2026-10-18 03:00 UTC is still Saturday 22:00 in Bogota.
    $compra = new CompraData(1000, 'restaurante', 1, null, CarbonImmutable::parse('2026-10-18 03:00', 'UTC'));

    expect($compra->fact('dia_semana'))->toBe(6)
        ->and($compra->fact('hora'))->toBe(22)
        ->and($compra->dayKey())->toBe('2026-10-17');
});

it('never holds with no conditions', function (): void {
    expect(app(CondicionesEvaluator::class)->holdAll([], SimuladorTestData::saturdayPurchase()))->toBeFalse();
});
