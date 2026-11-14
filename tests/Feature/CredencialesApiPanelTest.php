<?php

declare(strict_types=1);

use App\Actions\CredencialesApi\CreateCredencialApi;
use App\Enums\AlcanceApi;
use App\Enums\RolUsuario;
use App\Filament\Resources\CredencialesApi\Pages\ListCredencialesApi;
use App\Models\Auditoria;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

// Panel "Credenciales API" (E9-01, regla 4): crear y rotar muestran el token una vez, revocar
// lo corta, y cada administrador ve lo de su alcance. Las reglas de las Actions están en
// CredencialesApiTest.

beforeEach(function (): void {
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->rancho = Comercio::factory()->create(['razon_social' => 'El Rancho de Javi']);
    $this->sede = Sede::factory()->create(['comercio_id' => $this->rancho->id, 'nombre' => 'Principal']);
    $this->crear = fn (Sede $sede, string $nombre = 'POS caja 1'): User => app(CreateCredencialApi::class)(
        $this->admin,
        $nombre,
        $sede->id,
        [AlcanceApi::ComprasRegistrar],
    )->credencial;
});

it('creates a credential and shows its token once', function (): void {
    $this->actingAs($this->admin);

    $pagina = Livewire::test(ListCredencialesApi::class)
        ->callAction('crear', data: [
            'nombre' => 'POS caja 1',
            'sede_id' => $this->sede->id,
            'alcances' => [AlcanceApi::ComprasRegistrar->value, AlcanceApi::ClientesLeer->value],
        ])
        ->assertActionMounted('mostrarToken');

    $credencial = User::query()->where('rol', RolUsuario::Integracion->value)->sole();
    $token = $credencial->tokens()->sole();
    $mostrado = $pagina->instance()->mountedActions[0]['arguments']['token'] ?? '';

    expect($credencial->sede_id)->toBe($this->sede->id)
        ->and($credencial->comercio_id)->toBe($this->rancho->id)
        ->and($credencial->canAccessPanel(filament()->getPanel('admin')))->toBeFalse()
        ->and($credencial->puedeEntrarALaCaja())->toBeFalse()
        ->and($token->abilities)->toBe([AlcanceApi::ComprasRegistrar->value, AlcanceApi::ClientesLeer->value])
        // Sanctum arma el token como "id|prefijo…": el prefijo ayuda a los escáneres de secretos.
        ->and($mostrado)->toContain('|clubar_')
        // La base guarda solo la huella del token.
        ->and($token->token)->toBe(hash('sha256', explode('|', $mostrado, 2)[1]))
        ->and(Auditoria::query()->where('modelo_id', $credencial->id)->where('evento', 'creado')->exists())->toBeTrue();
});

it('rotates a token keeping the old one alive for the grace window', function (): void {
    $this->freezeSecond();
    $credencial = ($this->crear)($this->sede);
    $viejo = $credencial->tokens()->sole();
    $this->actingAs($this->admin);

    Livewire::test(ListCredencialesApi::class)
        ->callAction(TestAction::make('rotar')->table($credencial))
        ->assertActionMounted('mostrarToken');

    $tokens = $credencial->tokens()->orderBy('id')->get();
    $horas = config('api.rotation_grace_hours');

    expect($tokens)->toHaveCount(2)
        ->and($viejo->refresh()->expires_at?->equalTo(CarbonImmutable::now()->addHours($horas)))->toBeTrue()
        ->and($tokens->last()?->expires_at)->toBeNull()
        ->and($tokens->last()?->abilities)->toBe($viejo->abilities)
        ->and(Auditoria::query()->where('modelo_id', $credencial->id)->where('evento', 'credencial_rotada')->exists())
        ->toBeTrue();
});

it('revokes a credential and deletes its tokens', function (): void {
    $credencial = ($this->crear)($this->sede);
    $this->actingAs($this->admin);

    Livewire::test(ListCredencialesApi::class)
        ->callAction(TestAction::make('revocar')->table($credencial))
        ->assertNotified('Credencial revocada');

    expect($credencial->refresh()->activo)->toBeFalse()
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(Auditoria::query()->where('modelo_id', $credencial->id)->where('evento', 'credencial_revocada')->exists())
        ->toBeTrue();
});

it('shows a merchant administrator only the credentials of its merchant', function (): void {
    $propia = ($this->crear)($this->sede);
    $otraSede = Sede::factory()->create();
    $ajena = ($this->crear)($otraSede, 'POS ajeno');
    $this->actingAs(User::factory()->administradorDeComercio($this->rancho->id)->create());

    Livewire::test(ListCredencialesApi::class)
        ->assertCanSeeTableRecords([$propia])
        ->assertCanNotSeeTableRecords([$ajena]);
});
