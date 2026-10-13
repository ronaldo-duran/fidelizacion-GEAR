<?php

declare(strict_types=1);

use App\Reglas\ReglaDefinitionValidator;
use Tests\Support\ReglaTestData;

it('accepts every valid rule in the fixtures', function (string $tipo, array $definicion): void {
    expect(app(ReglaDefinitionValidator::class)->errors($tipo, $definicion))->toBe([]);
})->with(ReglaTestData::cases('reglas-validas.json'));

it('rejects every invalid rule in the fixtures', function (string $tipo, array $definicion): void {
    expect(app(ReglaDefinitionValidator::class)->errors($tipo, $definicion))->not->toBe([]);
})->with(ReglaTestData::cases('reglas-invalidas.json'));

it('uses the same schema in the application as in the fixture', function (): void {
    $application = json_decode((string) file_get_contents(resource_path('schemas/regla.json')), true);
    $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/schema-regla.json'), true);

    expect($application)->toBe($fixture);
});

it('accepts zero months of protection and the confirmed notices', function (): void {
    $niveles = collect(ReglaTestData::cases('reglas-validas.json'))
        ->first(static fn (array $case): bool => $case[0] === 'niveles');

    expect($niveles[1]['proteccion_meses'])->toBe(0)
        ->and($niveles[1]['avisos_dias_antes'])->toBe([30, 7, 1])
        ->and(app(ReglaDefinitionValidator::class)->errors('niveles', $niveles[1]))->toBe([]);
});

it('rejects a repeated or non-normalized category in the rates', function (array $tasas): void {
    $definicion = [
        'moneda_por_punto' => 1000,
        'tasas_por_categoria' => $tasas,
        'vigencia_puntos_meses' => 12,
    ];

    expect(app(ReglaDefinitionValidator::class)->errors('acumulacion', $definicion))->not->toBe([]);
})->with([
    'repeated' => [[
        ['categoria_slug' => 'restaurante', 'puntos_por_unidad' => 1],
        ['categoria_slug' => 'restaurante', 'puntos_por_unidad' => 2],
    ]],
    'uppercase' => [[['categoria_slug' => 'Restaurante', 'puntos_por_unidad' => 1]]],
]);

it('rejects an ordering comparison with text or a list outside the en operator', function (array $condicion): void {
    $definicion = ['multiplicador' => 2, 'condiciones' => [$condicion]];

    expect(app(ReglaDefinitionValidator::class)->errors('bonificacion', $definicion))->not->toBe([]);
})->with([
    'greater than with text' => [['campo' => 'monto', 'operador' => 'mayor', 'valor' => '50000']],
    'equals with a list' => [['campo' => 'comercio_id', 'operador' => 'igual', 'valor' => [1, 2]]],
    'en with an empty list' => [['campo' => 'dia_semana', 'operador' => 'en', 'valor' => []]],
]);
