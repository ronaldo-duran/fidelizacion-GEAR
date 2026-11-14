<?php

declare(strict_types=1);

use App\Filament\Resources\Comercios\Pages\CreateComercio;
use App\Filament\Resources\Comercios\Pages\EditComercio;
use App\Filament\Resources\Sedes\Pages\CreateSede;
use App\Filament\Resources\Sedes\Pages\EditSede;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

// Auditoría de seguridad 2026-11 (docs/seguridad/auditoria-2026-11.md, hallazgo 1): el
// alcance del administrador de comercio se exige en el servidor, no solo en lo que se ve.

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->propio = Comercio::factory()->create();
    $this->ajeno = Comercio::factory()->create();
    $this->admin = User::factory()->administradorDeComercio($this->propio->id)->create();
});

it('gives every panel resource a policy', function (): void {
    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        expect(Gate::getPolicyFor($resource::getModel()))
            ->not->toBeNull("{$resource} administra un modelo sin política.");
    }
});

it('does not let a merchant administrator create merchants', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CreateComercio::class)->assertForbidden();
});

it('keeps the category, the NIT and the active flag of a merchant for the group', function (): void {
    $otra = Categoria::factory()->create();
    $original = $this->propio->only(['categoria_id', 'nit', 'activo']);
    $this->actingAs($this->admin);

    Livewire::test(EditComercio::class, ['record' => $this->propio->id])
        ->set('data.categoria_id', $otra->id)
        ->set('data.nit', '900999000')
        ->set('data.activo', false)
        ->set('data.razon_social', 'Nombre nuevo')
        ->call('save')
        ->assertHasNoFormErrors();

    $guardado = $this->propio->refresh();

    expect($guardado->only(['categoria_id', 'nit', 'activo']))->toBe($original)
        ->and($guardado->razon_social)->toBe('Nombre nuevo');
});

it('lets the group change the category of a merchant', function (): void {
    $otra = Categoria::factory()->create();
    $this->actingAs(User::factory()->administradorDelGrupo()->create());

    Livewire::test(EditComercio::class, ['record' => $this->propio->id])
        ->fillForm(['categoria_id' => $otra->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->propio->refresh()->categoria_id)->toBe($otra->id);
});

it('does not let a merchant administrator open another merchant', function (): void {
    $this->actingAs($this->admin);

    $this->get("/admin/comercios/{$this->ajeno->id}/edit")->assertNotFound();
});

it('creates a branch only in the merchant of its administrator', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CreateSede::class)
        ->set('data.comercio_id', $this->ajeno->id)
        ->set('data.nombre', 'Intrusa')
        ->set('data.direccion', 'Calle 1')
        ->call('create');

    expect(Sede::query()->where('nombre', 'Intrusa')->exists())->toBeFalse();

    Livewire::test(CreateSede::class)
        ->fillForm(['nombre' => 'Sede norte', 'direccion' => 'Calle 2'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sede::query()->where('nombre', 'Sede norte')->sole()->comercio_id)->toBe($this->propio->id);
});

it('never moves a branch to another merchant, not even for the group', function (string $rol): void {
    $sede = Sede::factory()->create(['comercio_id' => $this->propio->id]);
    $this->actingAs($rol === 'grupo' ? User::factory()->administradorDelGrupo()->create() : $this->admin);

    Livewire::test(EditSede::class, ['record' => $sede->id])
        ->set('data.comercio_id', $this->ajeno->id)
        ->set('data.nombre', 'Renombrada')
        ->call('save');

    // Al administrador de comercio el formulario le rechaza el comercio ajeno; al grupo
    // se le guarda el resto y el comercio se descarta. En los dos casos la sede no se mueve.
    expect($sede->refresh()->comercio_id)->toBe($this->propio->id);
})->with(['comercio', 'grupo']);

it('does not let a merchant administrator edit a branch of another merchant', function (): void {
    $sede = Sede::factory()->create(['comercio_id' => $this->ajeno->id]);
    $this->actingAs($this->admin);

    $this->get("/admin/sedes/{$sede->id}/edit")->assertNotFound();
});
