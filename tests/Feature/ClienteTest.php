<?php

declare(strict_types=1);

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Illuminate\Database\UniqueConstraintViolationException;

it('identifies a customer by the document type and number together', function (): void {
    Cliente::query()->create(['tipo_documento' => TipoDocumento::CC, 'documento' => '1090123456']);
    $extranjero = Cliente::query()->create(['tipo_documento' => TipoDocumento::CE, 'documento' => '1090123456']);

    expect($extranjero->exists)->toBeTrue()
        ->and(Cliente::query()->where('documento', '1090123456')->count())->toBe(2);
});

it('rejects the same type and number twice in the database', function (): void {
    Cliente::query()->create(['tipo_documento' => TipoDocumento::PPT, 'documento' => 'PPT123']);

    $repetido = ['tipo_documento' => TipoDocumento::PPT, 'documento' => 'PPT123'];

    expect(fn (): Cliente => Cliente::query()->create($repetido))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('stores the document in uppercase and without spaces, so ppt123 and PPT 123 are the same', function (): void {
    $cliente = Cliente::query()->create(['tipo_documento' => TipoDocumento::PPT, 'documento' => ' ppt 123 ']);

    expect($cliente->documento)->toBe('PPT123')
        ->and(Cliente::normalizeDocumento('ppt 123'))->toBe('PPT123')
        ->and(fn (): Cliente => Cliente::query()->create(['tipo_documento' => 'PPT', 'documento' => 'PPT123']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('keeps the birth date as a date', function (): void {
    $cliente = Cliente::query()->create([
        'tipo_documento' => TipoDocumento::DEX,
        'documento' => 'V12345678',
        'fecha_nacimiento' => '1990-05-20',
    ]);

    expect($cliente->fresh()?->fecha_nacimiento?->toDateString())->toBe('1990-05-20')
        ->and($cliente->fresh()?->tipo_documento)->toBe(TipoDocumento::DEX);
});
