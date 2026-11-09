<?php

declare(strict_types=1);

use App\Actions\CredencialesApi\CreateCredencialApi;
use App\Actions\CredencialesApi\RevokeCredencialApi;
use App\Actions\CredencialesApi\RotateCredencialApi;
use App\Enums\AlcanceApi;
use App\Enums\RolUsuario;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Auditoria;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

// Credenciales de la API pública (E9-01, regla 4 y decisión 2): un usuario de integración por
// sede, con su token y sus permisos. Crear, rotar y revocar quedan en la auditoría.

beforeEach(function (): void {
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->rancho = Comercio::factory()->create();
    $this->sede = Sede::factory()->create(['comercio_id' => $this->rancho->id]);
    $this->crear = fn (?User $admin = null, ?Sede $sede = null): User => app(CreateCredencialApi::class)(
        $admin ?? $this->admin,
        'POS caja 1',
        ($sede ?? $this->sede)->id,
        [AlcanceApi::ComprasRegistrar, AlcanceApi::ClientesLeer],
    )->credencial;
});

it('keeps the rotation window equal to the fixture', function (): void {
    $fixture = json_decode((string) file_get_contents(dirname(__DIR__) . '/Fixtures/api-escenarios.json'), true);

    expect(config('api.rotation_grace_hours'))->toBe($fixture['settings']['rotation_grace_hours']['value']);
});

it('creates an integration user of the branch with a token shown once', function (): void {
    $emitida = app(CreateCredencialApi::class)($this->admin, ' POS caja 1 ', $this->sede->id, [
        AlcanceApi::ComprasRegistrar,
        AlcanceApi::ClientesLeer,
    ]);
    $credencial = $emitida->credencial;
    $token = PersonalAccessToken::query()->sole();

    expect($credencial->rol)->toBe(RolUsuario::Integracion)
        ->and($credencial->name)->toBe('POS caja 1')
        ->and($credencial->comercio_id)->toBe($this->rancho->id)
        ->and($credencial->sede_id)->toBe($this->sede->id)
        ->and($credencial->canAccessPanel(filament()->getPanel('admin')))->toBeFalse()
        ->and($credencial->puedeEntrarALaCaja())->toBeFalse()
        ->and($token->abilities)->toBe([AlcanceApi::ComprasRegistrar->value, AlcanceApi::ClientesLeer->value])
        // Sanctum arma el token como "id|prefijo…" y la base guarda solo la huella.
        ->and($emitida->token)->toContain('|clubar_')
        ->and($token->token)->toBe(hash('sha256', explode('|', $emitida->token, 2)[1]))
        ->and(Auditoria::query()->where('modelo_id', $credencial->id)->where('evento', 'creado')->exists())->toBeTrue();
});

it('asks for a name, a branch and at least one permission', function (): void {
    app(CreateCredencialApi::class)($this->admin, '  ', $this->sede->id, []);
})->throws(ValidationException::class);

it('rotates the token keeping the old one alive for the grace window', function (): void {
    $this->freezeSecond();
    $credencial = ($this->crear)();
    $viejo = $credencial->tokens()->sole();

    $emitida = app(RotateCredencialApi::class)($this->admin, $credencial->id);

    $nuevo = PersonalAccessToken::query()->whereKeyNot($viejo->id)->sole();

    expect($viejo->refresh()->expires_at?->equalTo(CarbonImmutable::now()->addHours(24)))->toBeTrue()
        ->and($nuevo->expires_at)->toBeNull()
        ->and($nuevo->abilities)->toBe($viejo->abilities)
        ->and($nuevo->token)->toBe(hash('sha256', explode('|', $emitida->token, 2)[1]))
        ->and(Auditoria::query()->where('modelo_id', $credencial->id)->where('evento', 'credencial_rotada')->exists())
        ->toBeTrue();
});

it('revokes a credential, deletes its tokens and does not rotate it again', function (): void {
    $credencial = ($this->crear)();

    app(RevokeCredencialApi::class)($this->admin, $credencial->id);

    expect($credencial->refresh()->activo)->toBeFalse()
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(Auditoria::query()->where('modelo_id', $credencial->id)->where('evento', 'credencial_revocada')->exists())
        ->toBeTrue();

    app(RotateCredencialApi::class)($this->admin, $credencial->id);
})->throws(ValidationException::class);

it('lets a merchant administrator manage only the credentials of its merchant', function (): void {
    $suyo = User::factory()->administradorDeComercio($this->rancho->id)->create();
    $propia = ($this->crear)($suyo);

    expect($propia->comercio_id)->toBe($this->rancho->id);

    ($this->crear)($suyo, Sede::factory()->create());
})->throws(AuthorizationException::class);

it('does not rotate or revoke a regular user', function (string $accion): void {
    $cajero = User::factory()->cajero($this->rancho->id, $this->sede->id)->create();

    app($accion)($this->admin, $cajero->id);
})->with([RotateCredencialApi::class, RevokeCredencialApi::class])->throws(AuthorizationException::class);

it('keeps the integration users out of the users list and its role out of the form', function (): void {
    $credencial = ($this->crear)();
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->admin])
        ->assertCanNotSeeTableRecords([$credencial]);

    expect(RolUsuario::asignables())->not->toHaveKey(RolUsuario::Integracion->value);
});
