<?php

declare(strict_types=1);

use App\Enums\TipoRegla;
use App\Models\Regla;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\Support\ReglaTestData;

it('stores the code normalized and the definition as given', function (): void {
    $regla = ReglaTestData::createVersionOne()->fresh();

    expect($regla?->codigo)->toBe('doble-fin-de-semana')
        ->and($regla?->definicion)->toEqual(ReglaTestData::bonificacion())
        ->and($regla?->vigente_hasta)->toBeNull();
});

it('does not create a version with a definition outside the schema', function (): void {
    expect(fn (): Regla => Regla::query()->create([
        'codigo' => 'x',
        'tipo' => TipoRegla::Bonificacion,
        'version' => 1,
        'vigente_desde' => CarbonImmutable::now(),
        'definicion' => ['multiplicador' => 2, 'condiciones' => 'monto > 50000'],
    ]))->toThrow(LogicException::class);

    expect(Regla::query()->count())->toBe(0);
});

it('does not allow editing the content of a version or deleting it', function (): void {
    $regla = ReglaTestData::createVersionOne();

    expect(fn (): bool => $regla->update(['definicion' => ReglaTestData::bonificacion(9)]))
        ->toThrow(LogicException::class)
        ->and(fn (): ?bool => $regla->fresh()?->delete())->toThrow(LogicException::class);

    expect($regla->fresh()?->definicion)->toEqual(ReglaTestData::bonificacion());
});

it('only allows closing the validity once', function (): void {
    $regla = ReglaTestData::createVersionOne();
    $regla->update(['vigente_hasta' => CarbonImmutable::parse('2026-10-13 09:00:00')]);

    expect(fn (): bool => (bool) $regla->fresh()?->update(['vigente_hasta' => CarbonImmutable::now()->addYear()]))
        ->toThrow(LogicException::class);
});

it('the database prevents two rows with the same code and version', function (): void {
    ReglaTestData::createVersionOne();

    expect(fn (): Regla => ReglaTestData::createVersionOne())->toThrow(UniqueConstraintViolationException::class);
});
