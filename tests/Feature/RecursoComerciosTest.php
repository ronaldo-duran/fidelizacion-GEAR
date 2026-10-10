<?php

declare(strict_types=1);

use App\Filament\Resources\Comercios\ComercioResource;
use App\Filament\Resources\Comercios\Pages\CreateComercio;
use App\Filament\Resources\Sedes\SedeResource;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('renderiza las tres listas para el administrador del grupo', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();
    Comercio::factory()->has(Sede::factory()->count(2))->create();

    $this->actingAs($grupo);
    $this->get('/admin/categorias')->assertSuccessful();
    $this->get('/admin/comercios')->assertSuccessful();
    $this->get('/admin/sedes')->assertSuccessful();
});

it('niega las categorías a un administrador de comercio', function (): void {
    $comercioAdmin = User::factory()->administradorDeComercio()->create();
    $this->actingAs($comercioAdmin);

    expect(ComercioResource::canAccess())->toBeTrue()
        ->and(App\Filament\Resources\Categorias\CategoriaResource::canAccess())->toBeFalse();
});

it('niega los comercios a un cajero', function (): void {
    $cajero = User::factory()->cajero()->create();

    $this->actingAs($cajero);
    expect(ComercioResource::canAccess())->toBeFalse()
        ->and(SedeResource::canAccess())->toBeFalse();
});

it('un administrador de comercio solo ve su propio comercio y sus sedes', function (): void {
    $comercioA = Comercio::factory()->create();
    $comercioB = Comercio::factory()->create();
    Sede::factory()->create(['comercio_id' => $comercioA->id]);
    Sede::factory()->create(['comercio_id' => $comercioB->id]);

    $admin = User::factory()->administradorDeComercio($comercioA->id)->create();
    $this->actingAs($admin);

    $comercios = ComercioResource::getEloquentQuery()->pluck('id')
        ->map(static fn (int|string $id): int => (int) $id);
    $sedes = SedeResource::getEloquentQuery()->pluck('comercio_id')
        ->map(static fn (int|string $id): int => (int) $id)
        ->unique()->values();

    expect($comercios->all())->toBe([$comercioA->id])
        ->and($sedes->all())->toBe([$comercioA->id]);
});

it('el grupo ve todos los comercios', function (): void {
    Comercio::factory()->count(3)->create();
    $grupo = User::factory()->administradorDelGrupo()->create();
    $this->actingAs($grupo);

    expect(ComercioResource::getEloquentQuery()->count())->toBe(3);
});

it('crea un comercio por el formulario', function (): void {
    $categoria = Categoria::factory()->create();
    $grupo = User::factory()->administradorDelGrupo()->create();
    $this->actingAs($grupo);

    Livewire::test(CreateComercio::class)
        ->fillForm([
            'razon_social' => 'Nuevo Comercio',
            'nit' => '901999888',
            'categoria_id' => $categoria->id,
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('comercios', ['nit' => '901999888']);
});
