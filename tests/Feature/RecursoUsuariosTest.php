<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('lista los usuarios para el administrador del grupo', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();
    User::factory()->cajero()->create();

    $this->actingAs($grupo)->get('/admin/users')->assertSuccessful();
});

it('muestra el formulario de creación', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();

    $this->actingAs($grupo)->get('/admin/users/create')->assertSuccessful();
});

it('muestra el formulario de edición', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();
    $otro = User::factory()->cajero()->create();

    $this->actingAs($grupo)->get("/admin/users/{$otro->id}/edit")->assertSuccessful();
});

it('crea un usuario normalizando el correo y cifrando la contraseña', function (): void {
    $comercio = App\Models\Comercio::factory()->create();
    $sede = App\Models\Sede::factory()->create(['comercio_id' => $comercio->id]);
    $grupo = User::factory()->administradorDelGrupo()->create();
    $this->actingAs($grupo);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Nuevo Cajero',
            'email' => 'CAJERO.NUEVO@ClubAponteRivera.CO',
            'rol' => RolUsuario::Cajero->value,
            'comercio_id' => $comercio->id,
            'sede_id' => $sede->id,
            'password' => 'secreto-largo',
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $creado = User::query()->where('name', 'Nuevo Cajero')->sole();

    expect($creado->email)->toBe('cajero.nuevo@clubaponterivera.co')
        ->and($creado->password)->not->toBe('secreto-largo')
        ->and(Hash::check('secreto-largo', $creado->password))->toBeTrue();
});

it('al editar deja la contraseña en blanco sin cambiarla', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();
    $cajero = User::factory()->cajero()->create();
    $hashOriginal = $cajero->password;
    $this->actingAs($grupo);

    Livewire::test(EditUser::class, ['record' => $cajero->id])
        ->fillForm(['name' => 'Cajero Renombrado', 'password' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cajero->refresh()->name)->toBe('Cajero Renombrado')
        ->and($cajero->password)->toBe($hashOriginal);
});
