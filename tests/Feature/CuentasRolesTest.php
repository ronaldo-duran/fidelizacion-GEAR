<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Filament\Resources\Users\UserResource;
use App\Models\Auditoria;
use App\Models\User;

it('deja entrar al panel al administrador del grupo', function (): void {
    $usuario = User::factory()->administradorDelGrupo()->create();

    $this->actingAs($usuario)->get('/admin')->assertSuccessful();
});

it('niega el panel a un cajero', function (): void {
    $cajero = User::factory()->cajero()->create();

    $this->actingAs($cajero)->get('/admin')->assertForbidden();
});

it('niega el panel a un usuario desactivado aunque tenga rol de acceso', function (): void {
    $inactivo = User::factory()->administradorDelGrupo()->inactivo()->create();

    expect($inactivo->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
    $this->actingAs($inactivo)->get('/admin')->assertForbidden();
});

it('solo el administrador del grupo gestiona usuarios', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();
    $comercio = User::factory()->administradorDeComercio()->create();

    $this->actingAs($grupo);
    expect(UserResource::canAccess())->toBeTrue();

    $this->actingAs($comercio);
    expect(UserResource::canAccess())->toBeFalse();
});

it('el alcance por comercio limita a su propio comercio', function (): void {
    $propio = App\Models\Comercio::factory()->create();
    $ajeno = App\Models\Comercio::factory()->create();

    $grupo = User::factory()->administradorDelGrupo()->create();
    $comercio = User::factory()->administradorDeComercio($propio->id)->create();

    expect($grupo->alcanzaComercio($ajeno->id))->toBeTrue()
        ->and($comercio->alcanzaComercio($propio->id))->toBeTrue()
        ->and($comercio->alcanzaComercio($ajeno->id))->toBeFalse();
});

it('audita la creación de un usuario con el autor y sin la contraseña', function (): void {
    $autor = User::factory()->administradorDelGrupo()->create();

    $this->actingAs($autor);
    $nuevo = User::factory()->cajero()->create();

    $registro = Auditoria::query()
        ->where('modelo', User::class)
        ->where('modelo_id', $nuevo->id)
        ->where('evento', 'creado')
        ->sole();

    expect($registro->usuario_id)->toBe($autor->id)
        ->and($registro->despues)->not->toHaveKey('password')
        ->and($registro->despues)->not->toHaveKey('remember_token');
});

it('audita una actualización guardando el antes y el después del cambio', function (): void {
    $usuario = User::factory()->cajero()->create(['rol' => RolUsuario::Cajero]);

    $usuario->update(['rol' => RolUsuario::AdministradorComercio]);

    $registro = Auditoria::query()
        ->where('modelo', User::class)
        ->where('modelo_id', $usuario->id)
        ->where('evento', 'actualizado')
        ->sole();

    expect($registro->antes['rol'])->toBe(RolUsuario::Cajero->value)
        ->and($registro->despues['rol'])->toBe(RolUsuario::AdministradorComercio->value);
});

it('no permite dos usuarios con el mismo correo', function (): void {
    User::factory()->create(['email' => 'repetido@clubaponterivera.co']);

    expect(fn (): User => User::factory()->create(['email' => 'repetido@clubaponterivera.co']))
        ->toThrow(Illuminate\Database\QueryException::class);
});
