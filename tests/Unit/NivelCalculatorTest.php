<?php

declare(strict_types=1);

use App\Enums\Nivel;
use App\Models\Cliente;
use App\Niveles\NivelCalculator;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->today = CarbonImmutable::parse('2026-10-17');
    $this->umbrales = ['umbrales' => [
        ['nivel' => 'bronce', 'umbral_puntos' => 0],
        ['nivel' => 'plata', 'umbral_puntos' => 500],
        ['nivel' => 'oro', 'umbral_puntos' => 1500],
        ['nivel' => 'diamante', 'umbral_puntos' => 3000],
    ]];
});

it('gives the level whose threshold the balance reaches', function (int $saldo, Nivel $expected): void {
    expect(app(NivelCalculator::class)->porPuntos($saldo, $this->umbrales))->toBe($expected);
})->with([
    'zero' => [0, Nivel::Bronce],
    'one below plata' => [499, Nivel::Bronce],
    'plata on the edge' => [500, Nivel::Plata],
    'one below oro' => [1499, Nivel::Plata],
    'oro on the edge' => [1500, Nivel::Oro],
    'diamante' => [3000, Nivel::Diamante],
]);

it('stays in bronce without a niveles rule', function (): void {
    expect(app(NivelCalculator::class)->porPuntos(10_000, []))->toBe(Nivel::Bronce);
});

it('only counts the membership and event sources while valid', function (): void {
    $cliente = app(Cliente::class)->forceFill([
        'saldo_puntos' => 600,
        'nivel_por_membresia' => 'diamante',
        'membresia_vigente_hasta' => '2026-10-16',
        'nivel_por_evento' => 'oro',
        'evento_vigente_hasta' => '2026-10-17',
    ]);

    $fuentes = app(NivelCalculator::class)->fuentes($cliente, $this->umbrales, $this->today);

    expect($fuentes->porPuntos)->toBe(Nivel::Plata)
        ->and($fuentes->porMembresia)->toBeNull()
        ->and($fuentes->porEvento)->toBe(Nivel::Oro)
        ->and($fuentes->nivel())->toBe(Nivel::Oro);
});
