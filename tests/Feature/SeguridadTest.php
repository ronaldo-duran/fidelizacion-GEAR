<?php

declare(strict_types=1);

use App\Actions\Verificacion\SendVerificationCode;
use App\Enums\PropositoVerificacion;
use App\Enums\RolUsuario;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Comercio;
use App\Models\Sede;
use App\Models\User;
use App\Panel\BusquedaClientes;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// Auditoría de seguridad 2026-11 (docs/seguridad/auditoria-2026-11.md), hallazgos 2 a 7.

it('sends the security headers on every surface', function (string $ruta): void {
    $respuesta = $this->get($ruta);

    $respuesta->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()')
        ->assertHeaderMissing('Strict-Transport-Security');
})->with(['/ingresar', '/caja/ingresar', '/admin/login', '/up']);

it('adds HSTS only in production over HTTPS', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    $this->get('https://club.test/ingresar')->assertHeader('Strict-Transport-Security', SecurityHeaders::HSTS);
    $this->get('http://club.test/ingresar')->assertHeaderMissing('Strict-Transport-Security');
});

it('ignores a forwarded IP unless the proxy is trusted', function (): void {
    Route::get('/_ip', static fn (): string => (string) request()->ip());
    $headers = ['X-Forwarded-For' => '203.0.113.50'];

    expect(config('app.trusted_proxies'))->toBe([])
        ->and($this->get('/_ip', $headers)->getContent())->toBe('127.0.0.1');

    TrustProxies::at(['127.0.0.1']);

    expect($this->get('/_ip', $headers)->getContent())->toBe('203.0.113.50');

    TrustProxies::at([]);
});

it('seeds a known password only in local and in tests', function (): void {
    expect(DatabaseSeeder::claveSembrada())->toBe('password');

    config(['muestra.clave_usuarios' => 'clave-de-staging-2026']);
    expect(DatabaseSeeder::claveSembrada())->toBe('clave-de-staging-2026');

    config(['muestra.clave_usuarios' => 'corta']);
    expect(static fn (): string => DatabaseSeeder::claveSembrada())->toThrow(RuntimeException::class);

    config(['muestra.clave_usuarios' => null]);
    $this->app->detectEnvironment(static fn (): string => 'staging');
    expect(static fn (): string => DatabaseSeeder::claveSembrada())->toThrow(RuntimeException::class);
});

it('rejects a weak password for a panel user', function (string $clave): void {
    Filament::setCurrentPanel('admin');
    $comercio = Comercio::factory()->create();
    $sede = Sede::factory()->create(['comercio_id' => $comercio->id]);
    $this->actingAs(User::factory()->administradorDelGrupo()->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Cajero débil',
            'email' => 'debil@example.com',
            'rol' => RolUsuario::Cajero->value,
            'comercio_id' => $comercio->id,
            'sede_id' => $sede->id,
            'password' => $clave,
            'activo' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);

    expect(User::query()->where('email', 'debil@example.com')->exists())->toBeFalse();
})->with(['corta1', 'sololetrasmuylargas', '1234567890123']);

it('never renders the debug page in production', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');
    config(['app.debug' => true]);

    (new App\Providers\AppServiceProvider($this->app))->boot();

    expect(config('app.debug'))->toBeFalse();
});

it('caps the SMS of one day whoever asks for them', function (): void {
    config(['verificacion.max_per_day_global' => 3, 'verificacion.max_per_ip_per_hour' => 100]);
    $resultados = [];

    foreach (range(1, 4) as $i) {
        try {
            app(SendVerificationCode::class)(PropositoVerificacion::Registro, "300000000{$i}", "10.0.0.{$i}");
            $resultados[] = 'sent';
        } catch (ValidationException) {
            $resultados[] = 'rejected';
        }
    }

    expect($resultados)->toBe(['sent', 'sent', 'sent', 'rejected']);
});

it('does not treat LIKE wildcards as a search for everyone', function (): void {
    $query = BusquedaClientes::aplicar(App\Models\Cliente::query(), '%');

    // Solo comodines: es una búsqueda vacía, no un patrón.
    expect($query->toBase()->wheres)->toBe([]);
});
