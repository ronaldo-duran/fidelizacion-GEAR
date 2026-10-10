<?php

declare(strict_types=1);

use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\User;

it('exige NIT único por comercio', function (): void {
    Comercio::factory()->create(['nit' => '900123456']);

    expect(fn (): Comercio => Comercio::factory()->create(['nit' => '900123456']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('normaliza el slug de categoría a minúsculas y lo exige único', function (): void {
    $categoria = Categoria::query()->create(['nombre' => 'Restaurante', 'slug' => 'Restaurante']);

    expect($categoria->slug)->toBe('restaurante');

    expect(fn (): Categoria => Categoria::query()->create(['nombre' => 'Otro', 'slug' => 'restaurante']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('un comercio inactivo no puede registrar compras', function (): void {
    $activo = Comercio::factory()->create();
    $inactivo = Comercio::factory()->inactivo()->create();

    expect($activo->puedeRegistrarCompras())->toBeTrue()
        ->and($inactivo->puedeRegistrarCompras())->toBeFalse();
});

it('el scope activos devuelve solo los comercios activos', function (): void {
    Comercio::factory()->count(2)->create();
    Comercio::factory()->inactivo()->create();

    expect(Comercio::query()->activos()->count())->toBe(2);
});

it('audita la creación de un comercio', function (): void {
    $autor = User::factory()->administradorDelGrupo()->create();
    $this->actingAs($autor);

    $comercio = Comercio::factory()->create();

    $this->assertDatabaseHas('auditorias', [
        'modelo' => Comercio::class,
        'modelo_id' => $comercio->id,
        'evento' => 'creado',
        'usuario_id' => $autor->id,
    ]);
});

it('un comercio tiene muchas sedes', function (): void {
    $comercio = Comercio::factory()->create();
    Sede::factory()->count(3)->create(['comercio_id' => $comercio->id]);

    expect($comercio->sedes()->count())->toBe(3);
});
