<?php

declare(strict_types=1);

use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Actions\Notificaciones\Notify;
use App\Enums\CanalNotificacion;
use App\Enums\EstadoEnvio;
use App\Enums\EventoNotificacion;
use App\Enums\MotivoCambioNivel;
use App\Jobs\SendAvisosNocturnos;
use App\Jobs\SendEnvioCorreo;
use App\Mail\CorreoNotificacion;
use App\Models\AvisoDescenso;
use App\Models\Cliente;
use App\Models\EnvioNotificacion;
use App\Models\PlantillaNotificacion;
use App\Models\SupresionCorreo;
use App\Notificaciones\RenderPlantilla;
use App\Notificaciones\ValidadorPlantilla;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BeneficiosTestData;
use Tests\Support\NivelesTestData;
use Tests\Support\RedencionTestData;

beforeEach(function (): void {
    $this->fixture = json_decode(
        (string) file_get_contents(__DIR__ . '/../Fixtures/notificaciones-escenarios.json'),
        true,
    );
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00:00'));
    $this->cliente = static function (?string $email = 'ana@example.com', array $fuentes = []): Cliente {
        $cliente = NivelesTestData::cliente($fuentes);
        $cliente->forceFill(['email' => $email, 'nombre_completo' => 'Ana María Gómez'])->save();

        return $cliente;
    };
    $this->envios = static fn (EventoNotificacion $evento): array => EnvioNotificacion::query()
        ->where('evento', $evento->value)
        ->get()
        ->keyBy(static fn (EnvioNotificacion $envio): string => $envio->canal->value)
        ->all();
});

it('keeps the events in config equal to the fixture', function (): void {
    $eventos = config('notificaciones.eventos');
    $entrega = $this->fixture['delivery'];

    foreach ($this->fixture['events'] as $evento) {
        expect($eventos[$evento['evento']])->toBe([
            'categoria' => $evento['categoria'],
            'origen' => $evento['origen'],
            'variables' => $evento['variables'],
            'asunto' => $evento['default']['asunto'],
            'cuerpo' => $evento['default']['cuerpo'],
        ]);
    }

    expect(array_keys($eventos))->toBe(array_column(EventoNotificacion::cases(), 'value'))
        ->and(config('notificaciones.hora_avisos'))->toBe($entrega['nightly_notices_send_at']['value'])
        ->and(config('notificaciones.intentos'))->toBe($entrega['max_attempts']['value'])
        ->and(config('notificaciones.limite_app'))->toBe($entrega['in_app_list_limit']['value']);
});

it('seeds one active template per event with the initial texts', function (): void {
    foreach (EventoNotificacion::cases() as $evento) {
        $plantilla = PlantillaNotificacion::query()->where('evento', $evento->value)->sole();

        expect($plantilla->asunto)->toBe($evento->asuntoInicial())
            ->and($plantilla->cuerpo)->toBe($evento->cuerpoInicial())
            ->and($plantilla->activa)->toBeTrue()
            ->and(app(ValidadorPlantilla::class)->errores($evento, $plantilla->asunto, $plantilla->cuerpo))->toBe([]);
    }
});

it('accepts only whitelisted variables in a template', function (array $caso): void {
    $errores = app(ValidadorPlantilla::class)->errores(
        EventoNotificacion::from($caso['evento']),
        $caso['asunto'],
        $caso['cuerpo'],
    );

    expect($errores === [])->toBe($caso['valid']);

    if (isset($caso['error_variable'])) {
        expect(implode(' ', $errores))->toContain('{' . $caso['error_variable'] . '}');
    }
})->with(fn (): array => collect(json_decode(
    (string) file_get_contents(__DIR__ . '/../Fixtures/notificaciones-escenarios.json'),
    true,
)['template_validation'])->keyBy('_description')->map(static fn (array $caso): array => [$caso])->all());

it('replaces the values once and escapes them in the email', function (): void {
    $render = app(RenderPlantilla::class);

    foreach ($this->fixture['rendering'] as $caso) {
        expect($render->texto($caso['asunto'], $caso['values']))->toBe($caso['expected_asunto']);

        if (isset($caso['expected_html_contains'])) {
            expect($render->html($render->texto('Hola, {nombre}', $caso['values'])))
                ->toContain($caso['expected_html_contains'])
                ->not->toContain('<b>');
        }
    }

    expect($render->html("Uno\nsigue.\n\nDos: https://club.test/inicio"))
        ->toBe("<p>Uno<br>\nsigue.</p>\n<p>Dos: <a href=\"https://club.test/inicio\">https://club.test/inicio</a></p>");
});

it('decides each channel by the email and the suppression list', function (array $caso): void {
    config(['notificaciones.eventos.bienvenida.categoria' => $caso['categoria']]);
    $cliente = ($this->cliente)($caso['email']);

    if ($caso['suppression'] !== null) {
        SupresionCorreo::query()->create([
            'email' => strtoupper((string) $caso['email']),
            'motivo' => $caso['suppression'],
        ]);
    }

    app(Notify::class)(EventoNotificacion::Bienvenida, $cliente, $cliente->id, ['nivel' => 'Bronce', 'enlace' => 'x']);

    $envios = ($this->envios)(EventoNotificacion::Bienvenida);
    $estado = static fn (EnvioNotificacion $envio): string => $envio->estado === EstadoEnvio::Omitido
        ? 'omitido_' . $envio->motivo
        : $envio->estado->value;

    expect($estado($envios['correo']))->toBe($caso['expected']['correo'])
        ->and($estado($envios['app']))->toBe($caso['expected']['app']);

    if ($caso['expected']['correo'] !== 'enviado') {
        Mail::assertNothingSent();

        return;
    }

    Mail::assertSent(CorreoNotificacion::class, static fn (CorreoNotificacion $m): bool => $m->hasTo($caso['email']));
})->with(fn (): array => collect(json_decode(
    (string) file_get_contents(__DIR__ . '/../Fixtures/notificaciones-escenarios.json'),
    true,
)['recipients'])->keyBy('_description')->map(static fn (array $caso): array => [$caso])->all());

it('sends the level-up notice with the template as it is now', function (): void {
    NivelesTestData::nivelesRegla();
    $cliente = ($this->cliente)('ana@example.com', ['nivel_por_puntos' => 'bronce', 'saldo_puntos' => 600]);
    PlantillaNotificacion::query()->where('evento', 'subida_nivel')->sole()
        ->update(['asunto' => 'Ya eres {nivel}, {nombre}']);

    app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoCompra);

    $envios = ($this->envios)(EventoNotificacion::SubidaNivel);
    expect($envios['correo']->asunto)->toBe('Ya eres Plata, Ana')
        ->and($envios['correo']->estado)->toBe(EstadoEnvio::Enviado)
        ->and($envios['correo']->intentos)->toBe(1)
        ->and($envios['app']->cuerpo)->toContain('Pasaste de Bronce a Plata');
    Mail::assertSent(
        CorreoNotificacion::class,
        static fn (CorreoNotificacion $mail): bool => $mail->hasSubject('Ya eres Plata, Ana'),
    );
});

it('sends no level-up notice when the promotion comes from a membership', function (): void {
    NivelesTestData::nivelesRegla();
    $cliente = ($this->cliente)('ana@example.com', ['nivel_por_puntos' => 'bronce', 'saldo_puntos' => 600]);

    app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoMembresia);

    expect($cliente->refresh()->nivel_efectivo->value)->toBe('plata')
        ->and(EnvioNotificacion::query()->count())->toBe(0);
});

it('sends nothing when the transaction that raised the level is rolled back', function (): void {
    NivelesTestData::nivelesRegla();
    $cliente = ($this->cliente)('ana@example.com', ['nivel_por_puntos' => 'bronce', 'saldo_puntos' => 600]);

    expect(static fn () => DB::transaction(static function () use ($cliente): void {
        app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoCompra);

        throw new RuntimeException('La compra falló.');
    }))->toThrow(RuntimeException::class);

    expect(EnvioNotificacion::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('notifies the same origin only once per channel', function (): void {
    $cliente = ($this->cliente)();
    $notify = app(Notify::class);

    DB::transaction(static function () use ($notify, $cliente): void {
        $notify(EventoNotificacion::Bienvenida, $cliente, $cliente->id, ['nivel' => 'Bronce', 'enlace' => 'x']);
        $notify(EventoNotificacion::Bienvenida, $cliente, $cliente->id, ['nivel' => 'Bronce', 'enlace' => 'x']);
    });

    expect(EnvioNotificacion::query()->count())->toBe(2);
    Mail::assertSentCount(1);
});

it('marks both channels as skipped when the template is inactive', function (): void {
    $cliente = ($this->cliente)();
    PlantillaNotificacion::query()->where('evento', 'bienvenida')->sole()->update(['activa' => false]);

    app(Notify::class)(EventoNotificacion::Bienvenida, $cliente, $cliente->id, ['nivel' => 'Bronce', 'enlace' => 'x']);

    foreach (($this->envios)(EventoNotificacion::Bienvenida) as $envio) {
        expect($envio->estado)->toBe(EstadoEnvio::Omitido)
            ->and($envio->motivo)->toBe(EnvioNotificacion::PLANTILLA_INACTIVA);
    }

    Mail::assertNothingSent();
});

it('retries a failing provider and leaves the email failed after the last attempt', function (): void {
    $cliente = ($this->cliente)(null);
    $envio = EnvioNotificacion::query()->create([
        'cliente_id' => $cliente->id,
        'evento' => EventoNotificacion::Bienvenida,
        'canal' => CanalNotificacion::Correo,
        'origen_id' => $cliente->id,
        'destinatario' => 'ana@example.com',
        'asunto' => 'Hola',
        'cuerpo' => 'Hola, Ana.',
        'estado' => EstadoEnvio::Pendiente,
    ]);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('El proveedor no responde.'));

    $primero = (new SendEnvioCorreo($envio->id))->withFakeQueueInteractions();
    $primero->handle();
    $primero->assertReleased(60);
    expect($envio->refresh()->estado)->toBe(EstadoEnvio::Pendiente)
        ->and($envio->error)->toBe('El proveedor no responde.');

    // El trabajo falso siempre va en el intento 1: con un solo intento permitido, ese es el último.
    config(['notificaciones.intentos' => 1]);
    $ultimo = (new SendEnvioCorreo($envio->id))->withFakeQueueInteractions();
    $ultimo->handle();
    $ultimo->assertNotReleased();

    expect($envio->refresh()->estado)->toBe(EstadoEnvio::Fallido)
        ->and($envio->intentos)->toBe(1);
});

it('sends the demotion notices registered last night once, and not the stale ones', function (): void {
    $cliente = ($this->cliente)();
    $aviso = static fn (int $diasAntes, CarbonImmutable $creado): AvisoDescenso => tap(
        AvisoDescenso::query()->make([
            'cliente_id' => $cliente->id,
            'fecha_vencimiento' => CarbonImmutable::today()->addDays($diasAntes),
            'dias_antes' => $diasAntes,
            'puntos_que_vencen' => 1200,
            'nivel_actual' => 'oro',
            'nivel_resultante' => 'plata',
        ]),
        static function (AvisoDescenso $aviso) use ($creado): void {
            $aviso->forceFill(['created_at' => $creado])->save();
        },
    );
    $deAnoche = $aviso(7, CarbonImmutable::parse('2026-11-03 01:35:00'));
    $viejo = $aviso(30, CarbonImmutable::parse('2026-11-01 01:35:00'));

    app(SendAvisosNocturnos::class)->handle(app(Notify::class));
    app(SendAvisosNocturnos::class)->handle(app(Notify::class));

    $envios = ($this->envios)(EventoNotificacion::AvisoDescenso);
    expect($envios['correo']->origen_id)->toBe($deAnoche->id)
        ->and($envios['correo']->asunto)->toBe('Tus puntos vencen el 10 de noviembre')
        ->and($envios['correo']->cuerpo)->toContain('vencen 1.200 puntos')
        ->and($envios['correo']->cuerpo)->toContain('pasarás de Oro a Plata')
        ->and($deAnoche->refresh()->enviado_at)->not->toBeNull()
        ->and($viejo->refresh()->enviado_at)->toBeNull()
        ->and(EnvioNotificacion::query()->count())->toBe(2);
    Mail::assertSentCount(1);
});

it('notifies the benefit used with its value', function (): void {
    RedencionTestData::seed();
    $caso = collect(RedencionTestData::fixture()['granted_value'])
        ->first(static fn (array $caso): bool => $caso['expected']['accepted']);
    $beneficio = BeneficiosTestData::beneficio($caso['beneficio']);
    $nit = collect(BeneficiosTestData::catalogo()['beneficios'])
        ->firstWhere('nombre', $caso['beneficio'])['comercio_nit'];
    $cliente = RedencionTestData::cliente([
        'tipo_documento' => 'CC',
        'documento' => '1099887766',
        'nivel_efectivo' => $caso['nivel'],
        'fecha_nacimiento' => CarbonImmutable::today()->subYears(30)->toDateString(),
    ]);
    $cliente->forceFill(['email' => 'ana@example.com'])->save();

    $redencion = RedencionTestData::entregar(
        RedencionTestData::cajero($nit),
        $cliente,
        $beneficio,
        $caso['monto_cuenta'],
        soporte: 'Contrato 1',
    );

    $correo = ($this->envios)(EventoNotificacion::BeneficioUsado)['correo'];
    expect($correo->origen_id)->toBe($redencion->id)
        ->and($correo->asunto)->toContain($beneficio->comercio()->value('razon_social'))
        ->and($correo->cuerpo)->toContain('«' . $beneficio->nombre . '»')
        ->and($correo->cuerpo)->toContain('$' . number_format($redencion->valor_otorgado_cop, 0, ',', '.'));
});
