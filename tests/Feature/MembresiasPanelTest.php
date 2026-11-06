<?php

declare(strict_types=1);

use App\Actions\Membresias\GrantMembresia;
use App\Enums\EstadoPago;
use App\Filament\Resources\Clientes\Pages\ViewCliente;
use App\Filament\Resources\Clientes\RelationManagers\MembresiasRelationManager;
use App\Filament\Resources\NotificacionesPago\Pages\ListNotificacionesPago;
use App\Filament\Resources\PagosMembresia\Pages\ListPagosMembresia;
use App\Filament\Resources\PlanesMembresia\Pages\CreatePlanMembresia;
use App\Filament\Resources\PlanesMembresia\Pages\EditPlanMembresia;
use App\Filament\Resources\PlanesMembresia\Pages\ListPlanesMembresia;
use App\Models\Auditoria;
use App\Models\Comercio;
use App\Models\Membresia;
use App\Models\NotificacionPago;
use App\Models\PagoMembresia;
use App\Models\PlanMembresia;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\MembresiasTestData;
use Tests\Support\NivelesTestData;

// El panel de membresías (E6-01): planes, pagos, notificaciones y los periodos del cliente.

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00'));
    MembresiasTestData::configurarWompi();
    NivelesTestData::nivelesRegla();
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->cliente = NivelesTestData::cliente([]);
    $this->actingAs($this->admin);
});

it('lets only the group administrator into the membership screens', function (): void {
    Livewire::test(ListPlanesMembresia::class)->assertCanSeeTableRecords(PlanMembresia::all());
    Livewire::test(ListPagosMembresia::class)->assertOk();
    Livewire::test(ListNotificacionesPago::class)->assertOk();

    $comercio = Comercio::factory()->create();

    $otros = [
        User::factory()->administradorDeComercio($comercio->id)->create(),
        User::factory()->cajero($comercio->id)->create(),
    ];

    foreach ($otros as $user) {
        $this->actingAs($user);

        foreach (['planes-membresia', 'pagos-membresia', 'notificaciones-pago'] as $slug) {
            $this->get("/admin/{$slug}")->assertForbidden();
        }
    }
});

it('creates and edits a plan, audits it and never deletes it', function (): void {
    Livewire::test(CreatePlanMembresia::class)
        ->fillForm([
            'nombre' => 'Diamante trimestral',
            'nivel' => 'diamante',
            'duracion_meses' => 3,
            'precio_cop' => 139000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = PlanMembresia::query()->where('nombre', 'Diamante trimestral')->sole();

    expect([$plan->duracion_meses, $plan->precio_cop, $plan->activo])->toBe([3, 139000, true]);

    Livewire::test(EditPlanMembresia::class, ['record' => $plan->getRouteKey()])
        ->assertActionDoesNotExist('delete')
        ->fillForm(['precio_cop' => 129000, 'activo' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($plan->refresh()->precio_cop)->toBe(129000)
        ->and($plan->activo)->toBeFalse()
        ->and(Auditoria::query()->where('modelo', PlanMembresia::class)->where('modelo_id', $plan->id)->count())
        ->toBe(2);
});

it('refuses a plan without a whole positive price or duration', function (): void {
    Livewire::test(CreatePlanMembresia::class)
        ->fillForm(['nombre' => 'Raro', 'nivel' => 'diamante', 'duracion_meses' => 0, 'precio_cop' => 0])
        ->call('create')
        ->assertHasFormErrors(['duracion_meses', 'precio_cop']);
});

it('lists the payments and the gateway notifications with their filters', function (): void {
    $aprobado = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    $this->postJson(
        route('webhooks.pagos', 'wompi'),
        MembresiasTestData::evento($aprobado->referencia, 'aprobado', $aprobado->monto_cop),
    )->assertOk();
    $pendiente = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan(12));
    $this->postJson(
        route('webhooks.pagos', 'wompi'),
        MembresiasTestData::evento('MNOEXISTE', 'aprobado', 49900, firmaValida: false),
    )->assertStatus(401);

    Livewire::test(ListPagosMembresia::class)
        ->assertCanSeeTableRecords([$aprobado, $pendiente])
        ->filterTable('estado', EstadoPago::Pendiente->value)
        ->assertCanSeeTableRecords([$pendiente])
        ->assertCanNotSeeTableRecords([$aprobado]);

    $invalida = NotificacionPago::query()->where('firma_valida', false)->sole();

    Livewire::test(ListNotificacionesPago::class)
        ->assertCanSeeTableRecords(NotificacionPago::all())
        ->filterTable('resultado', 'firma_invalida')
        ->assertCanSeeTableRecords([$invalida])
        ->assertCanNotSeeTableRecords(NotificacionPago::query()->where('firma_valida', true)->get());
});

it('shows the membership periods on the customer page', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan(), EstadoPago::Aprobado);
    app(GrantMembresia::class)($pago);

    Livewire::test(
        MembresiasRelationManager::class,
        ['ownerRecord' => $this->cliente, 'pageClass' => ViewCliente::class],
    )
        ->assertCanSeeTableRecords(Membresia::all())
        ->assertSee($pago->referencia)
        ->assertSee('2026-12-04');

    expect(PagoMembresia::query()->count())->toBe(1);
});
