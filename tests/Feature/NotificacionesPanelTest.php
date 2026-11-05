<?php

declare(strict_types=1);

use App\Actions\Notificaciones\Notify;
use App\Enums\EventoNotificacion;
use App\Filament\Resources\EnviosNotificacion\Pages\ListEnviosNotificacion;
use App\Filament\Resources\PlantillasNotificacion\Pages\EditPlantillaNotificacion;
use App\Filament\Resources\PlantillasNotificacion\Pages\ListPlantillasNotificacion;
use App\Livewire\Cliente\Avisos;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\EnvioNotificacion;
use App\Models\PlantillaNotificacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\LedgerTestData;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00:00'));
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->plantilla = PlantillaNotificacion::query()->where('evento', 'subida_nivel')->sole();
    $this->cliente = LedgerTestData::cliente();
    $this->cliente->forceFill(['nombre_completo' => 'Ana María Gómez', 'email' => 'ana@example.com'])->save();
    $this->avisar = fn (EventoNotificacion $evento, int $origenId, Cliente $cliente): mixed => app(Notify::class)(
        $evento,
        $cliente,
        $origenId,
        ['nivel_anterior' => 'Bronce', 'nivel' => 'Plata', 'enlace' => 'https://club.test/inicio'],
    );
});

it('lets only the group administrator manage templates and see deliveries', function (): void {
    $comercio = Comercio::factory()->create();
    $otros = [
        User::factory()->administradorDeComercio($comercio->id)->create(),
        User::factory()->cajero($comercio->id)->create(),
    ];

    $this->actingAs($this->admin);
    Livewire::test(ListPlantillasNotificacion::class)->assertCanSeeTableRecords(PlantillaNotificacion::all());
    Livewire::test(ListEnviosNotificacion::class)->assertOk();

    foreach ($otros as $user) {
        $this->actingAs($user);
        $this->get('/admin/plantillas-avisos')->assertForbidden();
        $this->get('/admin/avisos-enviados')->assertForbidden();
    }
});

it('saves a template with whitelisted variables and the next notice uses it', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(EditPlantillaNotificacion::class, ['record' => $this->plantilla->getRouteKey()])
        ->assertSee('{nivel_anterior}')
        ->fillForm(['asunto' => 'Ya eres {nivel}, {nombre}', 'cuerpo' => 'Pasaste a {nivel}.', 'activa' => true])
        ->assertSee('Ya eres Oro, Ana')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->plantilla->refresh()->asunto)->toBe('Ya eres {nivel}, {nombre}')
        ->and(Auditoria::query()
            ->where('modelo', PlantillaNotificacion::class)
            ->where('modelo_id', $this->plantilla->id)
            ->exists())->toBeTrue();

    ($this->avisar)(EventoNotificacion::SubidaNivel, 1, $this->cliente);

    expect(EnvioNotificacion::query()->where('canal', 'correo')->sole()->asunto)->toBe('Ya eres Plata, Ana');
});

it('refuses a variable outside the whitelist and keeps the template as it was', function (): void {
    $this->actingAs($this->admin);
    $antes = $this->plantilla->asunto;

    Livewire::test(EditPlantillaNotificacion::class, ['record' => $this->plantilla->getRouteKey()])
        ->fillForm(['asunto' => 'Hola {documento}', 'cuerpo' => 'Pasaste a {nivel}.', 'activa' => true])
        ->call('save')
        ->assertHasFormErrors(['asunto']);

    expect($this->plantilla->refresh()->asunto)->toBe($antes);
});

it('lists the deliveries with their state and lets support filter them', function (): void {
    $sinCorreo = LedgerTestData::cliente('2020202020');
    ($this->avisar)(EventoNotificacion::SubidaNivel, 1, $this->cliente);
    ($this->avisar)(EventoNotificacion::SubidaNivel, 2, $sinCorreo);
    $this->actingAs($this->admin);

    Livewire::test(ListEnviosNotificacion::class)
        ->assertCanSeeTableRecords(EnvioNotificacion::all())
        ->assertSee('Sin correo')
        ->filterTable('estado', 'omitido')
        ->assertCanSeeTableRecords(EnvioNotificacion::query()->where('estado', 'omitido')->get())
        ->assertCanNotSeeTableRecords(EnvioNotificacion::query()->where('estado', 'enviado')->get());
});

it('shows the customer their notices, marks them read and only theirs', function (): void {
    $otro = LedgerTestData::cliente('3030303030');
    ($this->avisar)(EventoNotificacion::SubidaNivel, 1, $this->cliente);
    ($this->avisar)(EventoNotificacion::SubidaNivel, 2, $otro);
    $this->actingAs($this->cliente, 'cliente');

    $this->get('/inicio')->assertSee('Avisos, 1 sin leer');

    Livewire::test(Avisos::class)
        ->assertSee('Subiste a Plata')
        ->assertSee('Nuevo')
        ->assertSee('<a href="https://club.test/inicio">', false);

    expect(EnvioNotificacion::query()->where('cliente_id', $this->cliente->id)->where('canal', 'app')->sole()->leido_at)
        ->not->toBeNull()
        ->and(EnvioNotificacion::query()->where('cliente_id', $otro->id)->where('canal', 'app')->sole()->leido_at)
        ->toBeNull();

    $this->get('/inicio')->assertDontSee('sin leer');
    Livewire::test(Avisos::class)->assertSee('Subiste a Plata')->assertDontSee('Nuevo');
});

it('shows an empty inbox and asks a guest to sign in', function (): void {
    $this->get('/avisos')->assertRedirect('/ingresar');

    $this->actingAs($this->cliente, 'cliente');

    $this->get('/avisos')->assertOk()->assertSee('No tienes avisos');
});
