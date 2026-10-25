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
    $comercio = App\Models\Comercio::factory()->create();
    $sede = App\Models\Sede::factory()->create(['comercio_id' => $comercio->id]);
    $cajero = User::factory()->cajero($comercio->id, $sede->id)->create();
    $hashOriginal = $cajero->password;
    $this->actingAs($grupo);

    Livewire::test(EditUser::class, ['record' => $cajero->id])
        ->fillForm(['name' => 'Cajero Renombrado', 'password' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cajero->refresh()->name)->toBe('Cajero Renombrado')
        ->and($cajero->password)->toBe($hashOriginal);
});

it('elige el comercio y la sede de una lista y rechaza una sede de otro comercio sin error 500', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();
    $comercio = App\Models\Comercio::factory()->create();
    $otro = App\Models\Comercio::factory()->create();
    $sedeAjena = App\Models\Sede::factory()->create(['comercio_id' => $otro->id]);
    App\Models\Sede::factory()->count(2)->create(['comercio_id' => $comercio->id]);
    $this->actingAs($grupo);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Cajero',
            'email' => 'cajero.otro@clubaponterivera.co',
            'rol' => RolUsuario::Cajero->value,
            'comercio_id' => $comercio->id,
            'sede_id' => $sedeAjena->id,
            'password' => 'secreto-largo',
        ])
        ->call('create')
        ->assertHasFormErrors(['sede_id']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Cajero',
            'email' => 'cajero.otro@clubaponterivera.co',
            'rol' => RolUsuario::Cajero->value,
            'comercio_id' => 999999,
            'password' => 'secreto-largo',
        ])
        ->call('create')
        ->assertHasFormErrors(['comercio_id', 'sede_id']);

    expect(User::query()->where('email', 'cajero.otro@clubaponterivera.co')->exists())->toBeFalse();
});

it('deja elegida la sede cuando el comercio tiene una sola', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();
    $comercio = App\Models\Comercio::factory()->create();
    $sede = App\Models\Sede::factory()->create(['comercio_id' => $comercio->id]);
    $this->actingAs($grupo);

    Livewire::test(CreateUser::class)
        ->fillForm(['rol' => RolUsuario::Cajero->value])
        ->set('data.comercio_id', $comercio->id)
        ->assertSet('data.sede_id', $sede->id);
});

it('muestra comercio y sede según el rol y limpia los que el rol no usa', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();
    $comercio = App\Models\Comercio::factory()->create();
    $sede = App\Models\Sede::factory()->create(['comercio_id' => $comercio->id]);
    $cajero = User::factory()->cajero($comercio->id, $sede->id)->create();
    $this->actingAs($grupo);

    Livewire::test(CreateUser::class)
        ->fillForm(['rol' => RolUsuario::AdministradorGrupo->value])
        ->assertFormFieldIsHidden('comercio_id')
        ->assertFormFieldIsHidden('sede_id')
        ->fillForm(['rol' => RolUsuario::AdministradorComercio->value])
        ->assertFormFieldIsVisible('comercio_id')
        ->assertFormFieldIsHidden('sede_id');

    Livewire::test(EditUser::class, ['record' => $cajero->id])
        ->fillForm(['rol' => RolUsuario::AdministradorGrupo->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cajero->refresh()->comercio_id)->toBeNull()
        ->and($cajero->sede_id)->toBeNull();
});
