<?php

declare(strict_types=1);

use App\Actions\Notificaciones\Notify;
use App\Enums\CanalNotificacion;
use App\Enums\EventoNotificacion;
use App\Jobs\Nocturno\RegisterAvisosRenovacion;
use App\Jobs\SendAvisosNocturnos;
use App\Models\AvisoRenovacion;
use App\Models\Cliente;
use App\Models\EnvioNotificacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\Support\MembresiasTestData;
use Tests\Support\NivelesTestData;

// El aviso de renovación de la membresía (E6-01, regla 10): lo registra el proceso nocturno y
// lo envía el de la mañana (E7-01).

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 01:30:00'));
    $this->dias = MembresiasTestData::fixture()['renewal']['notice_days_before']['value'];
    $this->conMembresia = static function (string $hasta): Cliente {
        $cliente = NivelesTestData::cliente([]);
        $cliente->forceFill(['nivel_por_membresia' => 'diamante', 'membresia_vigente_hasta' => $hasta])->save();

        return $cliente->refresh();
    };
    $this->registrar = static fn () => Bus::dispatchSync(new RegisterAvisosRenovacion('2026-11-05'));
});

it('keeps the notice days of the configuration equal to the fixture', function (): void {
    expect(config('pagos.avisos_renovacion_dias'))->toBe($this->dias);
});

it('registers a notice once for each membership that ends on a notice day', function (): void {
    $porDia = collect($this->dias)->mapWithKeys(fn (int $dias): array => [
        $dias => ($this->conMembresia)(CarbonImmutable::parse('2026-11-05')->addDays($dias)->toDateString()),
    ]);
    ($this->conMembresia)('2026-11-20');

    ($this->registrar)();
    ($this->registrar)();

    expect(AvisoRenovacion::query()->count())->toBe(count($this->dias));

    foreach ($porDia as $dias => $cliente) {
        $aviso = AvisoRenovacion::query()->where('cliente_id', $cliente->id)->sole();

        expect($aviso->dias_antes)->toBe($dias)
            ->and($aviso->vigente_hasta->toDateString())->toBe($cliente->membresia_vigente_hasta?->toDateString())
            ->and($aviso->enviado_at)->toBeNull();
    }
});

it('owes no notice to a customer who already paid the next period', function (): void {
    $cliente = ($this->conMembresia)('2026-11-12');

    // Renovó antes de que venciera: el periodo nuevo corre el fin más lejano.
    $cliente->forceFill(['membresia_vigente_hasta' => '2026-12-12'])->save();
    ($this->registrar)();

    expect(AvisoRenovacion::query()->count())->toBe(0);
});

it('sends the renewal notice in the morning with the link to the subscription, once', function (): void {
    $cliente = ($this->conMembresia)('2026-11-12');
    ($this->registrar)();

    $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00:00'));
    app(SendAvisosNocturnos::class)->handle(app(Notify::class));
    app(SendAvisosNocturnos::class)->handle(app(Notify::class));

    $aviso = AvisoRenovacion::query()->sole();
    $envio = EnvioNotificacion::query()
        ->where('evento', EventoNotificacion::MembresiaPorVencer->value)
        ->where('canal', CanalNotificacion::App->value)
        ->sole();

    expect($aviso->enviado_at)->not->toBeNull()
        ->and($envio->cliente_id)->toBe($cliente->id)
        ->and($envio->origen_id)->toBe($aviso->id)
        ->and($envio->asunto)->toBe('Tu membresía vence el 12 de noviembre de 2026')
        ->and($envio->cuerpo)->toContain(route('cliente.membresia'));
});

it('does not send the renewal notice to a customer who renewed after it was registered', function (): void {
    $cliente = ($this->conMembresia)('2026-11-12');
    ($this->registrar)();

    // Pagó la renovación antes del envío de la mañana: su membresía ya va un mes más.
    $cliente->forceFill(['membresia_vigente_hasta' => '2026-12-12'])->save();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00:00'));
    app(SendAvisosNocturnos::class)->handle(app(Notify::class));

    expect(AvisoRenovacion::query()->sole()->enviado_at)->toBeNull()
        ->and(EnvioNotificacion::query()->count())->toBe(0);
});

it('does not send late the renewal notices of a missed morning', function (): void {
    ($this->conMembresia)('2026-11-12');
    ($this->registrar)();

    $this->travelTo(CarbonImmutable::parse('2026-11-07 08:00:00'));
    app(SendAvisosNocturnos::class)->handle(app(Notify::class));

    expect(AvisoRenovacion::query()->sole()->enviado_at)->toBeNull()
        ->and(EnvioNotificacion::query()->count())->toBe(0);
});
