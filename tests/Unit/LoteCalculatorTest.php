<?php

declare(strict_types=1);

use App\Enums\TipoMovimiento;
use App\Puntos\LoteCalculator;
use Carbon\CarbonImmutable;
use Tests\Support\LedgerTestData;

it('keeps a positive movement as a lot', function (): void {
    $lots = app(LoteCalculator::class)->remaining([
        LedgerTestData::movimiento(1, ['tipo' => TipoMovimiento::Acumulacion, 'puntos' => 85]),
    ]);

    expect($lots)->toBe([1 => 85]);
});

it('takes a reversal only from its own purchase', function (): void {
    $lots = app(LoteCalculator::class)->remaining([
        LedgerTestData::movimiento(1, ['tipo' => TipoMovimiento::Acumulacion, 'puntos' => 85, 'transaccion_id' => 10]),
        LedgerTestData::movimiento(2, ['tipo' => TipoMovimiento::Acumulacion, 'puntos' => 50, 'transaccion_id' => 20]),
        LedgerTestData::movimiento(3, ['tipo' => TipoMovimiento::Reversa, 'puntos' => -85, 'transaccion_id' => 10]),
    ]);

    expect($lots)->toBe([1 => 0, 2 => 50]);
});

it('takes a negative adjustment from the lot that expires first', function (): void {
    $lots = app(LoteCalculator::class)->remaining([
        LedgerTestData::movimiento(1, [
            'tipo' => TipoMovimiento::Acumulacion,
            'puntos' => 300,
            'vigencia_hasta' => '2027-06-01',
        ]),
        LedgerTestData::movimiento(2, [
            'tipo' => TipoMovimiento::Acumulacion,
            'puntos' => 200,
            'vigencia_hasta' => '2027-01-01',
        ]),
        LedgerTestData::movimiento(3, ['tipo' => TipoMovimiento::AjusteManual, 'puntos' => -150]),
    ]);

    expect($lots)->toBe([1 => 300, 2 => 50]);
});

it('takes an expiry from expired lots first', function (): void {
    $lots = app(LoteCalculator::class)->remaining([
        LedgerTestData::movimiento(1, [
            'tipo' => TipoMovimiento::Acumulacion,
            'puntos' => 300,
            'vigencia_hasta' => '2027-06-01',
        ]),
        LedgerTestData::movimiento(2, [
            'tipo' => TipoMovimiento::Acumulacion,
            'puntos' => 200,
            'vigencia_hasta' => '2026-10-10',
        ]),
        LedgerTestData::movimiento(3, [
            'tipo' => TipoMovimiento::Caducidad,
            'puntos' => -200,
            'created_at' => CarbonImmutable::parse('2026-10-11 02:00'),
        ]),
    ]);

    expect($lots)->toBe([1 => 300, 2 => 0]);
});

it('reports the remaining points of one purchase only', function (): void {
    $remaining = app(LoteCalculator::class)->remainingForTransaccion([
        LedgerTestData::movimiento(1, ['tipo' => TipoMovimiento::Acumulacion, 'puntos' => 85, 'transaccion_id' => 10]),
        LedgerTestData::movimiento(2, ['tipo' => TipoMovimiento::AjusteManual, 'puntos' => -85]),
        LedgerTestData::movimiento(3, ['tipo' => TipoMovimiento::Acumulacion, 'puntos' => 50, 'transaccion_id' => 20]),
    ], 10);

    expect($remaining)->toBe([1 => 0]);
});
