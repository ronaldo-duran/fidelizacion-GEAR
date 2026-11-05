<?php

declare(strict_types=1);

use App\Membresias\PeriodoCalculator;
use Carbon\CarbonImmutable;
use Tests\Support\MembresiasTestData;

dataset('periods', static fn (): array => collect(MembresiasTestData::fixture()['periods'])
    ->mapWithKeys(static fn (array $caso): array => [$caso['_description'] => [$caso]])
    ->all());

it('works out the dates of a membership period', function (array $caso): void {
    $actual = $caso['current_vigente_hasta'];

    [$desde, $hasta] = app(PeriodoCalculator::class)->periodo(
        CarbonImmutable::parse($caso['approved_on']),
        $actual === null ? null : CarbonImmutable::parse($actual),
        $caso['duracion_meses'],
    );

    expect($desde->toDateString())->toBe($caso['expected_vigente_desde'])
        ->and($hasta->toDateString())->toBe($caso['expected_vigente_hasta']);
})->with('periods');
