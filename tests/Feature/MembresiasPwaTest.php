<?php

declare(strict_types=1);

use App\Actions\Membresias\StartPagoMembresia;
use App\Enums\CanalNotificacion;
use App\Enums\EstadoPago;
use App\Enums\EventoNotificacion;
use App\Enums\Nivel;
use App\Livewire\Cliente\EstadoPago as PantallaEstadoPago;
use App\Livewire\Cliente\Membresia;
use App\Models\EnvioNotificacion;
use App\Models\Membresia as PeriodoMembresia;
use App\Models\PagoMembresia;
use App\Models\PlanMembresia;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\MembresiasTestData;
use Tests\Support\NivelesTestData;

// La compra de la membresía en la PWA y su estado (E6-01, PR 2).

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00'));
    MembresiasTestData::configurarWompi();
    NivelesTestData::nivelesRegla();
    $this->cliente = NivelesTestData::cliente(['saldo_puntos' => 1160, 'nivel_por_puntos' => 'plata']);
    $this->actingAs($this->cliente, 'cliente');
    $this->simulada = static fn () => config([
        'pagos.pasarela' => 'simulada',
        'pagos.simulada' => true,
        'pagos.pasarelas.simulada' => App\Pagos\SimuladaPasarela::class,
    ]);
});

it('starts a pending payment with a copy of the plan and sends the customer to Wompi', function (): void {
    $plan = MembresiasTestData::plan();

    $url = app(StartPagoMembresia::class)($this->cliente, $plan->id);

    $pago = PagoMembresia::query()->sole();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($pago->estado)->toBe(EstadoPago::Pendiente)
        ->and($pago->referencia)->toMatch('/^M[0-9A-Z]{26}$/')
        ->and($pago->pasarela)->toBe('wompi')
        ->and([$pago->nivel, $pago->duracion_meses, $pago->monto_cop])->toBe([Nivel::Diamante, 1, 49900])
        ->and($url)->toStartWith('https://checkout.wompi.co/p/?')
        ->and($query['reference'])->toBe($pago->referencia)
        ->and($query['amount-in-cents'])->toBe('4990000')
        ->and($query['redirect-url'])->toBe(route('cliente.membresia.pago', $pago->referencia))
        ->and(PeriodoMembresia::query()->count())->toBe(0)
        ->and($this->cliente->fresh()?->nivel_efectivo)->toBe(Nivel::Plata);
});

it('keeps the price of the payment when the plan changes later', function (): void {
    $plan = MembresiasTestData::plan();
    app(StartPagoMembresia::class)($this->cliente, $plan->id);

    $plan->update(['precio_cop' => 59900]);

    expect(PagoMembresia::query()->sole()->monto_cop)->toBe(49900);
});

it('refuses an inactive plan and a plan of another level while a membership is valid', function (): void {
    $inactivo = MembresiasTestData::plan();
    $inactivo->update(['activo' => false]);

    expect(fn () => app(StartPagoMembresia::class)($this->cliente, $inactivo->id))
        ->toThrow(ValidationException::class, 'Elige uno de los planes disponibles.');

    $oro = PlanMembresia::query()->create([
        'nombre' => 'Oro mensual', 'nivel' => 'oro', 'duracion_meses' => 1, 'precio_cop' => 19900,
    ]);
    $this->cliente->forceFill(['nivel_por_membresia' => 'diamante', 'membresia_vigente_hasta' => '2026-11-20'])->save();

    expect(fn () => app(StartPagoMembresia::class)($this->cliente, $oro->id))
        ->toThrow(ValidationException::class, 'Tu membresía Diamante va hasta el 20 de noviembre de 2026');

    expect(PagoMembresia::query()->count())->toBe(0);
});

it('shows the plans, the points that keep counting and the price of the chosen plan', function (): void {
    $anual = MembresiasTestData::plan(12);

    Livewire::test(Membresia::class)
        ->assertSee('Suscripción Diamante')
        ->assertSee('Mensual')
        ->assertSee('Anual')
        ->assertSee('Tus 1.160 puntos siguen contando.')
        ->assertSee('Si llegas a 3.000 comprando, conservas Diamante sin pagar.')
        ->assertSee('Continuar al pago · $49.900')
        ->set('plan', $anual->id)
        ->assertSee('Continuar al pago · $499.000');
});

it('redirects to the gateway when the customer continues to the payment', function (): void {
    Livewire::test(Membresia::class)
        ->call('pagar')
        ->assertRedirectContains('https://checkout.wompi.co/p/');

    expect(PagoMembresia::query()->sole()->cliente_id)->toBe($this->cliente->id);
});

it('shows the pending payment and refreshes itself until the webhook arrives', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());

    $pantalla = Livewire::test(PantallaEstadoPago::class, ['referencia' => $pago->referencia])
        ->assertSee('Estamos confirmando tu pago')
        ->assertSeeHtml('wire:poll.3s');

    $this->postJson(route('webhooks.pagos', 'wompi'), MembresiasTestData::evento($pago->referencia, 'aprobado', 49900))
        ->assertOk();

    $pantalla->call('$refresh')
        ->assertSee('Ya eres Diamante')
        ->assertSee('Tu membresía va hasta el 4 de diciembre de 2026.')
        ->assertDontSeeHtml('wire:poll.3s');
});

it('offers to try again after a declined payment', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan(), EstadoPago::Rechazado);

    Livewire::test(PantallaEstadoPago::class, ['referencia' => $pago->referencia])
        ->assertSee('No pudimos confirmar tu pago')
        ->assertSee('Intentar de nuevo');
});

it('shows only the customer\'s own payments', function (): void {
    $otro = NivelesTestData::cliente([]);
    $pago = MembresiasTestData::pago($otro, MembresiasTestData::plan());

    $this->get(route('cliente.membresia.pago', $pago->referencia))->assertNotFound();
});

it('sends the membership confirmed notice in the same transaction as the period', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());

    $this->postJson(route('webhooks.pagos', 'wompi'), MembresiasTestData::evento($pago->referencia, 'aprobado', 49900))
        ->assertOk();

    $aviso = EnvioNotificacion::query()
        ->where('evento', EventoNotificacion::MembresiaConfirmada->value)
        ->where('canal', CanalNotificacion::App->value)
        ->sole();

    expect($aviso->origen_id)->toBe($pago->id)
        ->and($aviso->asunto)->toBe('Tu membresía Diamante mensual está activa')
        ->and($aviso->cuerpo)->toContain('Recibimos tu pago de $49.900. Eres Diamante hasta el 4 de diciembre de 2026');
});

it('approves a payment through the simulated gateway only when it is enabled', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    $pago->forceFill(['pasarela' => 'simulada'])->save();

    $this->get(route('cliente.pagos.simulada', $pago->referencia))->assertNotFound();

    ($this->simulada)();

    $this->get(route('cliente.pagos.simulada', $pago->referencia))->assertOk()->assertSee('Pasarela de prueba');
    $this->post(route('cliente.pagos.simulada', $pago->referencia), ['resultado' => 'aprobar'])
        ->assertRedirect(route('cliente.membresia.pago', $pago->referencia));

    expect($pago->fresh()?->estado)->toBe(EstadoPago::Aprobado)
        ->and(PeriodoMembresia::query()->sole()->pago_id)->toBe($pago->id)
        ->and($this->cliente->fresh()?->nivel_efectivo)->toBe(Nivel::Diamante);
});

it('starts the payment in the simulated gateway and lets it be declined', function (): void {
    ($this->simulada)();

    $url = app(StartPagoMembresia::class)($this->cliente, MembresiasTestData::plan()->id);
    $pago = PagoMembresia::query()->sole();

    expect($pago->pasarela)->toBe('simulada');

    $this->post($url, ['resultado' => 'rechazar'])
        ->assertRedirect(route('cliente.membresia.pago', $pago->referencia));
    $this->post(
        route('cliente.pagos.simulada', ['referencia' => $pago->referencia, 'regreso' => 'https://otro.test/robo']),
        ['resultado' => 'rechazar'],
    )->assertRedirect(route('cliente.membresia.pago', $pago->referencia));

    expect($pago->fresh()?->estado)->toBe(EstadoPago::Rechazado)
        ->and(PeriodoMembresia::query()->count())->toBe(0);
});

it('never accepts a webhook for the simulated gateway', function (): void {
    ($this->simulada)();
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    $pago->forceFill(['pasarela' => 'simulada'])->save();

    $this->postJson(route('webhooks.pagos', 'simulada'), ['referencia' => $pago->referencia])->assertStatus(401);

    expect($pago->fresh()?->estado)->toBe(EstadoPago::Pendiente);
});

it('links the subscription from the home screen', function (): void {
    $this->get(route('cliente.inicio'))
        ->assertOk()
        ->assertSee(route('cliente.membresia'))
        ->assertSee('Desde $49.900 al mes, sin cobro automático.');
});
