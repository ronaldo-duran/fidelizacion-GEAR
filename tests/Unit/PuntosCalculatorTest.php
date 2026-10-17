<?php

declare(strict_types=1);

use App\Enums\TipoRegla;
use App\Reglas\CompraData;
use App\Reglas\PuntosCalculator;
use App\Reglas\PuntosDesglose;
use App\Reglas\ReglaAplicada;
use Carbon\CarbonImmutable;
use Tests\Support\SimuladorTestData as Data;

it('matches the fixture for the rules in force and for the draft', function (string $set, string $expectedKey): void {
    $rules = Data::currentRules();

    if ($set === 'draft') {
        $draft = Data::draft();
        $others = array_filter($rules, static fn (ReglaAplicada $rule): bool => $rule->codigo !== $draft->codigo);
        $rules = [...array_values($others), $draft];
    }

    $porDia = [];
    $purchases = Data::purchases();

    foreach (Data::fixture()['purchases'] as $index => $scenario) {
        $purchase = $purchases[$index];
        $desglose = app(PuntosCalculator::class)->calculate($purchase, $rules, $porDia[$purchase->dayKey()] ?? 0);
        $porDia[$purchase->dayKey()] = ($porDia[$purchase->dayKey()] ?? 0) + $desglose->puntos;
        $expected = $scenario[$expectedKey];

        expect([
            'base' => $desglose->base,
            'bonus_rule' => $desglose->bonificacion?->codigo,
            'cap' => $desglose->tope,
            'puntos' => $desglose->puntos,
        ])->toBe($expected, $scenario['_description']);
    }
})->with([
    'rules in force' => ['current', 'expected_current'],
    'draft' => ['draft', 'expected_draft'],
]);

it('rounds the base down to whole points', function (): void {
    $desglose = app(PuntosCalculator::class)->calculate(Data::restaurantPurchase(12_999), [Data::acumulacion()]);

    expect($desglose->base)->toBe(12)->and($desglose->puntos)->toBe(12);
});

it('applies only the highest multiplier when several bonuses hold', function (): void {
    $rules = [
        Data::acumulacion(),
        Data::bonus(2, 'doble', 2),
        Data::bonus(3, 'quintuple', 5),
        Data::bonus(4, 'triple', 3),
    ];

    $desglose = app(PuntosCalculator::class)->calculate(Data::restaurantPurchase(10_000), $rules);

    expect($desglose->bonificacion?->codigo)->toBe('quintuple')
        ->and($desglose->multiplicador)->toBe(5)
        ->and($desglose->puntos)->toBe(50);
});

it('keeps the first bonus on a tie', function (): void {
    $rules = [Data::acumulacion(), Data::bonus(2, 'doble-a', 2), Data::bonus(3, 'doble-b', 2)];

    $desglose = app(PuntosCalculator::class)->calculate(Data::restaurantPurchase(10_000), $rules);

    expect($desglose->bonificacion?->codigo)->toBe('doble-a');
});

it('skips a bonus whose conditions do not all hold', function (): void {
    $rules = [Data::acumulacion(), Data::bonus(2, 'doble-grande', 2, 50_000)];

    $desglose = app(PuntosCalculator::class)->calculate(Data::restaurantPurchase(49_999), $rules);

    expect($desglose->bonificacion)->toBeNull()->and($desglose->puntos)->toBe(49);
});

it('applies the caps in order and reports the one that limited last', function (): void {
    $rule = Data::acumulacion(['topes' => [
        'puntos_por_transaccion' => 100,
        'puntos_por_dia' => 150,
        'puntos_por_mes' => 400,
    ]]);
    $calculator = app(PuntosCalculator::class);

    $transaccion = $calculator->calculate(Data::restaurantPurchase(300_000), [$rule]);
    $dia = $calculator->calculate(Data::restaurantPurchase(300_000), [$rule], 80);
    $mes = $calculator->calculate(Data::restaurantPurchase(300_000), [$rule], 0, 390);
    $agotado = $calculator->calculate(Data::restaurantPurchase(300_000), [$rule], 150);

    expect([$transaccion->tope, $transaccion->puntos])->toBe(['puntos_por_transaccion', 100])
        ->and([$dia->tope, $dia->puntos])->toBe(['puntos_por_dia', 70])
        ->and([$mes->tope, $mes->puntos])->toBe(['puntos_por_mes', 10])
        ->and([$agotado->tope, $agotado->puntos])->toBe(['puntos_por_dia', 0]);
});

it('cuts the bonus points first when a cap limits', function (): void {
    $rules = [Data::acumulacion(['topes' => ['puntos_por_transaccion' => 120]]), Data::bonus(2, 'triple', 3)];

    $desglose = app(PuntosCalculator::class)->calculate(Data::restaurantPurchase(100_000), $rules);

    expect($desglose->puntos)->toBe(120)
        ->and($desglose->basePuntos())->toBe(100)
        ->and($desglose->extraPuntos())->toBe(20);
});

it('gives no bonus when the base is zero', function (): void {
    $rules = [Data::acumulacion(), Data::bonus(2, 'doble', 2)];

    $desglose = app(PuntosCalculator::class)->calculate(Data::restaurantPurchase(999), $rules);

    expect($desglose->bonificacion)->toBeNull()->and($desglose->puntos)->toBe(0);
});

it('gives zero points to a category without a rate', function (): void {
    $purchase = new CompraData(100_000, 'colegio', 1, null, CarbonImmutable::parse('2026-10-13 10:00'));

    expect(app(PuntosCalculator::class)->calculate($purchase, [Data::acumulacion()])->puntos)->toBe(0);
});

it('gives nothing without an accrual rule', function (): void {
    $rules = [Data::bonus(2, 'doble', 2)];

    $desglose = app(PuntosCalculator::class)->calculate(Data::restaurantPurchase(100_000), $rules);

    expect($desglose)->toEqual(PuntosDesglose::none());
});

it('refuses two accrual rules at once', function (): void {
    $otra = new ReglaAplicada(9, 'otra', 1, TipoRegla::Acumulacion, Data::acumulacion()->definicion);
    $rules = [Data::acumulacion(), $otra];

    $purchase = Data::restaurantPurchase(1000);

    expect(fn (): PuntosDesglose => app(PuntosCalculator::class)->calculate($purchase, $rules))
        ->toThrow(LogicException::class);
});

it('labels a draft instead of a version number', function (): void {
    expect(ReglaAplicada::draft('x', TipoRegla::Acumulacion, [])->versionLabel())->toBe('borrador')
        ->and(Data::acumulacion()->versionLabel())->toBe('v1');
});
