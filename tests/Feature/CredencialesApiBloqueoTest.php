<?php

declare(strict_types=1);

use App\Actions\CredencialesApi\CreateCredencialApi;
use App\Actions\CredencialesApi\RevokeCredencialApi;
use App\Actions\CredencialesApi\RotateCredencialApi;
use App\Enums\AlcanceApi;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\BloqueoTestData;
use Tests\Support\SinTransaccionDePrueba;

// E9-01, regla 4: rotar y revocar la misma credencial se ordenan por el bloqueo de su fila. Una
// rotación que choca con una revocación nunca deja un token vivo en una credencial revocada. Los
// datos se guardan de verdad para que la segunda conexión los vea.
uses(SinTransaccionDePrueba::class);

beforeEach(function (): void {
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->credencial = app(CreateCredencialApi::class)(
        $this->admin,
        'POS caja 1',
        Sede::factory()->create()->id,
        [AlcanceApi::ComprasRegistrar],
    )->credencial;
});

afterEach(function (): void {
    BloqueoTestData::vaciar();
});

it('waits for the credential lock before rotating or revoking', function (string $accion): void {
    // Otro administrador tiene la credencial bloqueada: la acción espera ahí, antes de tocar los tokens.
    $segunda = BloqueoTestData::segundaConexion();
    $segunda->beginTransaction();
    $segunda->table('users')->where('id', $this->credencial->id)->lockForUpdate()->first();
    BloqueoTestData::limitarEspera(DB::connection(), 1);

    $bloqueada = null;

    try {
        app($accion)($this->admin, $this->credencial->id);
    } catch (QueryException $error) {
        $bloqueada = $error;
    }

    expect($bloqueada)->toBeInstanceOf(QueryException::class)
        ->and($bloqueada?->getSql())->toContain('users')
        ->and(PersonalAccessToken::query()->count())->toBe(1);

    $segunda->rollBack();
})->with([RotateCredencialApi::class, RevokeCredencialApi::class]);

it('does not rotate a credential revoked by another administrator', function (): void {
    // Otro administrador la revoca desde su conexión.
    $segunda = BloqueoTestData::segundaConexion();
    $segunda->beginTransaction();
    $segunda->table('users')->where('id', $this->credencial->id)->lockForUpdate()->first();
    $segunda->table('personal_access_tokens')->where('tokenable_id', $this->credencial->id)->delete();
    $segunda->table('users')->where('id', $this->credencial->id)->update(['activo' => false]);
    $segunda->commit();

    // Al bloquear la fila, la rotación ve la credencial revocada y no emite nada.
    expect(fn () => app(RotateCredencialApi::class)($this->admin, $this->credencial->id))
        ->toThrow(ValidationException::class, 'Esta credencial está revocada.')
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});
