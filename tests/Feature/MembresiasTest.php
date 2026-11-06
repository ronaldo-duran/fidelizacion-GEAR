<?php

declare(strict_types=1);

use App\Actions\Membresias\GrantMembresia;
use App\Enums\EstadoPago;
use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use App\Enums\ResultadoNotificacionPago;
use App\Models\CambioNivel;
use App\Models\Membresia;
use App\Models\MovimientoPuntos;
use App\Models\NotificacionPago;
use App\Models\PagoMembresia;
use App\Models\PlanMembresia;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\Support\MembresiasTestData;
use Tests\Support\NivelesTestData;

// Membresías y webhook de la pasarela (E6-01), contra los casos del fixture.

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00:00'));
    MembresiasTestData::configurarWompi();
    NivelesTestData::nivelesRegla();
    $this->cliente = NivelesTestData::cliente(['nivel_por_puntos' => 'plata']);
    $this->webhook = fn (array $evento) => $this->postJson(route('webhooks.pagos', 'wompi'), $evento);
});

it('seeds the initial plans of the fixture', function (): void {
    $planes = PlanMembresia::query()->orderBy('duracion_meses')->get()
        ->map(static fn (PlanMembresia $plan): array => [
            'nombre' => $plan->nombre,
            'nivel' => $plan->nivel->value,
            'duracion_meses' => $plan->duracion_meses,
            'precio_cop' => $plan->precio_cop,
        ])
        ->all();
    $esperados = collect(MembresiasTestData::fixture()['plans'])
        ->map(static fn (array $plan): array => collect($plan)->except('_description')->all())
        ->all();

    expect($planes)->toBe($esperados)
        ->and(config('pagos.planes_iniciales'))->toBe($esperados)
        ->and(PlanMembresia::query()->where('activo', false)->count())->toBe(0);
});

dataset('notifications', static fn (): array => collect(MembresiasTestData::fixture()['notifications'])
    ->mapWithKeys(static fn (array $caso): array => [$caso['_description'] => [$caso]])
    ->all());

it('applies the gateway notifications of the fixture', function (array $caso): void {
    $pago = $caso['pago'] === null
        ? null
        : MembresiasTestData::pago(
            $this->cliente,
            MembresiasTestData::plan(),
            EstadoPago::from($caso['pago']['estado']),
        );
    $saldo = $this->cliente->saldo_puntos;
    $periodos = Membresia::query()->count();
    $aviso = $caso['notification'];

    $response = ($this->webhook)(MembresiasTestData::evento(
        $pago->referencia ?? 'MNOEXISTE',
        $aviso['status'],
        $aviso['amount_cop'],
        $aviso['currency'],
        $aviso['signature_valid'],
    ));

    $esperado = $caso['expected'];
    $response->assertStatus($esperado['response_status'] ?? 200);
    expect(Membresia::query()->count() - $periodos)->toBe($esperado['periods_created'])
        ->and(NotificacionPago::query()->count())->toBe(1);

    if (isset($esperado['pago_estado'])) {
        expect($pago?->fresh()?->estado)->toBe(EstadoPago::from($esperado['pago_estado']));
    }

    if (isset($esperado['logged_as'])) {
        expect(NotificacionPago::query()->sole()->resultado)
            ->toBe(ResultadoNotificacionPago::from($esperado['logged_as']));
    }

    if (isset($esperado['nivel_efectivo'])) {
        expect($this->cliente->fresh()?->nivel_efectivo)->toBe(Nivel::from($esperado['nivel_efectivo']));
    }

    expect($this->cliente->fresh()?->saldo_puntos)->toBe($saldo);
})->with('notifications');

it('grants the membership and promotes the customer in the same request', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());

    ($this->webhook)(MembresiasTestData::evento($pago->referencia, 'aprobado', 49900))->assertOk();

    $cliente = $this->cliente->fresh();
    $membresia = Membresia::query()->sole();
    $pago->refresh();

    expect($pago->estado)->toBe(EstadoPago::Aprobado)
        ->and($pago->id_externo)->toBe('12345-1700000000-10001')
        ->and($pago->aprobado_at?->toDateTimeString())->toBe('2026-11-03 10:00:00')
        ->and($membresia->pago_id)->toBe($pago->id)
        ->and($membresia->nivel)->toBe(Nivel::Diamante)
        ->and($membresia->vigente_desde->toDateString())->toBe('2026-11-03')
        ->and($membresia->vigente_hasta->toDateString())->toBe('2026-12-02')
        ->and($cliente?->nivel_por_membresia)->toBe(Nivel::Diamante)
        ->and($cliente?->membresia_vigente_hasta?->toDateString())->toBe('2026-12-02')
        ->and($cliente?->nivel_por_puntos)->toBe(Nivel::Plata)
        ->and($cliente?->nivel_efectivo)->toBe(Nivel::Diamante)
        ->and(CambioNivel::query()->sole()->motivo)->toBe(MotivoCambioNivel::AscensoMembresia)
        ->and(MovimientoPuntos::query()->count())->toBe(0)
        ->and(NotificacionPago::query()->sole()->resultado)->toBe(ResultadoNotificacionPago::Aplicado);
});

it('extends the validity only once when the same webhook arrives twice', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    $evento = MembresiasTestData::evento($pago->referencia, 'aprobado', 49900);

    ($this->webhook)($evento)->assertOk();
    ($this->webhook)($evento)->assertOk();

    expect(Membresia::query()->count())->toBe(1)
        ->and($this->cliente->fresh()?->membresia_vigente_hasta?->toDateString())->toBe('2026-12-02')
        ->and(NotificacionPago::query()->orderBy('id')->pluck('resultado')->all())
        ->toBe([ResultadoNotificacionPago::Aplicado, ResultadoNotificacionPago::Repetido]);
});

it('stacks a renewal paid before the membership ends', function (): void {
    $primero = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    ($this->webhook)(MembresiasTestData::evento($primero->referencia, 'aprobado', 49900, idExterno: 'tx-1'))
        ->assertOk();

    $this->travelTo(CarbonImmutable::parse('2026-11-28 09:00:00'));
    $renovacion = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan(12));
    ($this->webhook)(MembresiasTestData::evento($renovacion->referencia, 'aprobado', 499000, idExterno: 'tx-2'))
        ->assertOk();

    $periodo = Membresia::query()->where('pago_id', $renovacion->id)->sole();

    expect($periodo->vigente_desde->toDateString())->toBe('2026-12-03')
        ->and($periodo->vigente_hasta->toDateString())->toBe('2027-12-02')
        ->and($this->cliente->fresh()?->membresia_vigente_hasta?->toDateString())->toBe('2027-12-02')
        ->and(CambioNivel::query()->count())->toBe(1);
});

it('never creates a second period for the same payment', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan(), EstadoPago::Aprobado);
    app(GrantMembresia::class)($pago);

    expect(fn () => app(GrantMembresia::class)($pago))->toThrow(UniqueConstraintViolationException::class)
        ->and(Membresia::query()->count())->toBe(1);
});

it('applies an approved retry of a declined payment with the same reference', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    $evento = static fn (string $estado, string $idExterno): array => MembresiasTestData::evento(
        $pago->referencia,
        $estado,
        $pago->monto_cop,
        idExterno: $idExterno,
    );

    ($this->webhook)($evento('rechazado', 'tx-1'))->assertOk();
    ($this->webhook)($evento('rechazado', 'tx-1'))->assertOk();
    ($this->webhook)($evento('aprobado', 'tx-2'))->assertOk();

    expect($pago->fresh()?->estado)->toBe(EstadoPago::Aprobado)
        ->and($pago->fresh()?->id_externo)->toBe('tx-2')
        ->and(Membresia::query()->sole()->pago_id)->toBe($pago->id)
        ->and(NotificacionPago::query()->orderBy('id')->pluck('resultado')->all())->toBe([
            ResultadoNotificacionPago::Rechazado,
            ResultadoNotificacionPago::Repetido,
            ResultadoNotificacionPago::Aplicado,
        ]);
});

it('logs for review a second charge, an unknown status and the transaction of another payment', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    $otro = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan(12));
    $aprobar = fn (string $idExterno, string $estado = 'aprobado') => ($this->webhook)(
        MembresiasTestData::evento($pago->referencia, $estado, $pago->monto_cop, idExterno: $idExterno),
    )->assertOk();

    ($this->webhook)(MembresiasTestData::evento($otro->referencia, 'rechazado', $otro->monto_cop, idExterno: 'tx-otro'))
        ->assertOk();
    $aprobar('tx-otro');
    $aprobar('tx-1', 'SOMETHING_NEW');
    $aprobar('tx-1');
    $aprobar('tx-2');

    expect(Membresia::query()->count())->toBe(1)
        ->and($pago->fresh()?->id_externo)->toBe('tx-1')
        ->and(NotificacionPago::query()->orderBy('id')->pluck('resultado')->all())->toBe([
            ResultadoNotificacionPago::Rechazado,
            ResultadoNotificacionPago::TransicionInvalida,
            ResultadoNotificacionPago::TransicionInvalida,
            ResultadoNotificacionPago::Aplicado,
            ResultadoNotificacionPago::TransicionInvalida,
        ]);
});

it('does not store a large body with an invalid signature', function (): void {
    $evento = MembresiasTestData::evento('MNOEXISTE', 'aprobado', 49900, firmaValida: false);
    $evento['relleno'] = str_repeat('x', 20000);

    ($this->webhook)($evento)->assertStatus(401);

    expect(NotificacionPago::query()->sole()->payload)->toMatchArray(['truncado' => true]);
});

it('logs an invalid signature with the payload and changes nothing', function (): void {
    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());

    ($this->webhook)(MembresiasTestData::evento($pago->referencia, 'aprobado', 49900, firmaValida: false))
        ->assertStatus(401);

    $registro = NotificacionPago::query()->sole();

    expect($registro->firma_valida)->toBeFalse()
        ->and($registro->referencia)->toBe($pago->referencia)
        ->and($registro->payload['data']['transaction']['status'])->toBe('APPROVED')
        ->and($pago->fresh()?->estado)->toBe(EstadoPago::Pendiente);
});

it('answers 404 to an unknown gateway and needs no session or CSRF token', function (): void {
    $this->post('/webhooks/pagos/otra', [])->assertNotFound();

    $pago = MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    $response = $this->call(
        'POST',
        '/webhooks/pagos/wompi',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode(MembresiasTestData::evento($pago->referencia, 'aprobado', 49900), JSON_THROW_ON_ERROR),
    );

    $response->assertOk()->assertCookieMissing(config('session.cookie'));
    expect(PagoMembresia::query()->sole()->estado)->toBe(EstadoPago::Aprobado);
});

it('keeps many pending payments without an external id side by side', function (): void {
    $otro = NivelesTestData::cliente([]);

    MembresiasTestData::pago($this->cliente, MembresiasTestData::plan());
    MembresiasTestData::pago($this->cliente, MembresiasTestData::plan(12));
    MembresiasTestData::pago($otro, MembresiasTestData::plan());

    expect(PagoMembresia::query()->whereNull('id_externo')->count())->toBe(3);
});

it('logs only a few invalid signatures per minute and keeps answering 401', function (): void {
    $evento = MembresiasTestData::evento('MNOEXISTE', 'aprobado', 49900, firmaValida: false);

    foreach (range(1, 31) as $intento) {
        ($this->webhook)($evento)->assertStatus(401);
    }

    expect(NotificacionPago::query()->count())->toBe(30);

    $this->travel(61)->seconds();
    ($this->webhook)($evento)->assertStatus(401);

    expect(NotificacionPago::query()->count())->toBe(31);
});
