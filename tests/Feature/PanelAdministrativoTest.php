<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ActividadTablero;
use App\Filament\Widgets\ClientesPorNivelTablero;
use App\Filament\Widgets\KpisTablero;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\TableroTestData;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('shows the login of the design', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('logo del grupo')
        ->assertSee('Ingreso al sistema')
        ->assertSee('Administradores del Grupo Aponte Rivera')
        ->assertSee('Correo')
        ->assertSee('Contraseña')
        ->assertSee('Mantener la sesión en este celular')
        ->assertSee('Ingresar')
        ->assertSee('Olvidé mi contraseña')
        ->assertSee('bg-tier-diamante-bg', false);
});

it('lets an active admin in and keeps cashiers and inactive accounts out with the same message', function (): void {
    $admin = User::factory()->administradorDelGrupo()->create(['email' => 'admin@prueba.co']);
    User::factory()->cajero()->create(['email' => 'cajero@prueba.co']);
    User::factory()->administradorDelGrupo()->create(['email' => 'inactivo@prueba.co', 'activo' => false]);
    $intento = static fn (string $email, string $password = 'password') => Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate');

    $sinCuenta = $intento('nadie@prueba.co')->errors()->get('data.email');

    expect($intento('cajero@prueba.co')->errors()->get('data.email'))->toBe($sinCuenta)
        ->and($intento('inactivo@prueba.co')->errors()->get('data.email'))->toBe($sinCuenta)
        ->and($intento('admin@prueba.co', 'otra')->errors()->get('data.email'))->toBe($sinCuenta);

    $intento('admin@prueba.co')->assertHasNoErrors();

    expect(Filament::auth()->id())->toBe($admin->id);
});

it('answers the password reset the same whether the email exists or not', function (): void {
    Notification::fake();
    $admin = User::factory()->administradorDelGrupo()->create(['email' => 'admin@prueba.co']);
    $respuesta = 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña.';

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'nadie@prueba.co'])
        ->call('request')
        ->assertNotified($respuesta);

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'admin@prueba.co'])
        ->call('request')
        ->assertNotified($respuesta);

    Notification::assertSentTo($admin, ResetPassword::class);
    Notification::assertCount(1);
});

it('orders the navigation as in 1z and hides groups without resources', function (): void {
    $this->actingAs(User::factory()->administradorDelGrupo()->create());

    $navegacion = collect(Filament::getCurrentOrDefaultPanel()->getNavigation())
        ->mapWithKeys(static fn ($grupo): array => [
            (string) ($grupo->getLabel() ?? '') => collect($grupo->getItems())->map->getLabel()->values()->all(),
        ])
        ->all();

    expect($navegacion)->toBe([
        '' => ['Tablero', 'Clientes'],
        'Beneficios' => ['Beneficios'],
        'Comercios' => ['Comercios', 'Sedes', 'Categorías'],
        'Niveles y reglas' => ['Reglas', 'Simulador', 'Eventos calificados'],
        'Configuración' => ['Usuarios'],
    ]);
});

it('renders the group dashboard with its period selector', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00'));

    $this->actingAs(User::factory()->administradorDelGrupo()->create())
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('Tablero del grupo')
        ->assertSee('Octubre 2026 · vs. septiembre');
});

it('fills the dashboard widgets for the chosen period', function (): void {
    $case = TableroTestData::fixtureCase('group dashboard for a period');
    TableroTestData::seed($case);
    $this->travelTo(CarbonImmutable::parse($case['now']));
    $this->actingAs(User::factory()->administradorDelGrupo()->create());
    $filtros = ['pageFilters' => ['periodo' => $case['period']]];

    Livewire::test(KpisTablero::class, $filtros)
        ->assertSee('Clientes inscritos')
        ->assertSee('+2 nuevos en el mes')
        ->assertSee('+75 % vs. agosto')
        ->assertSee('0 por cada 100 compras');
    Livewire::test(ClientesPorNivelTablero::class, $filtros)
        ->assertSee('De 2 Diamante: 1 por puntos, 1 por suscripción, 0 por inmueble')
        ->assertSee('33 %');
    Livewire::test(ActividadTablero::class, $filtros)
        ->assertSee('Comercios por actividad')
        ->assertSee('Heladería Polar')
        ->assertSee('Sin registros hace 10 días')
        ->assertDontSee('Comercio cerrado');
});

it('shows a merchant admin only its merchant, by branch', function (): void {
    $case = TableroTestData::fixtureCase('group dashboard for a period');
    $data = TableroTestData::seed($case);
    $this->travelTo(CarbonImmutable::parse($case['now']));
    $this->actingAs(User::factory()->administradorDeComercio($data->comercios['A'])->create());
    $filtros = ['pageFilters' => ['periodo' => $case['period']]];

    $this->get(Dashboard::getUrl())->assertOk()->assertSee('Tablero de El Rancho de Javi');
    Livewire::test(KpisTablero::class, $filtros)
        ->assertSee('Clientes que compraron')
        ->assertSee('+50 % vs. agosto')
        ->assertDontSee('Clientes inscritos');
    Livewire::test(ActividadTablero::class, $filtros)
        ->assertSee('Sedes por actividad')
        ->assertSee('A1')
        ->assertDontSee('Cervecería BBC');
});

it('loads with zeros and no errors for an empty program', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 09:00:00'));
    $this->actingAs(User::factory()->administradorDelGrupo()->create());

    Livewire::test(KpisTablero::class)->assertOk()->assertSee('$0');
    Livewire::test(ClientesPorNivelTablero::class)->assertOk()->assertSee('De 0 Diamante');
    Livewire::test(ActividadTablero::class)->assertOk()->assertSee('Todavía no hay comercios activos.');
});
