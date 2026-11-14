<?php

declare(strict_types=1);

use App\Actions\Clientes\SetFechaNacimiento;
use App\Actions\Clientes\SetPreferenciaAviso;
use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Actions\Puntos\RecalculateSaldo;
use App\Enums\EstadoEnvio;
use App\Enums\MotivoCambioNivel;
use App\Livewire\Cliente\Historial;
use App\Livewire\Cliente\Perfil;
use App\Models\Cliente;
use App\Models\EnvioNotificacion;
use App\Models\PreferenciaAviso;
use App\Puntos\FilaHistorial;
use App\Puntos\HistorialCliente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\HistorialTestData;
use Tests\Support\NivelesTestData;

// Historial y perfil del cliente (E8-05). El cliente, sus movimientos y lo que se espera ver salen
// de tests/Fixtures/historial-perfil-escenarios.json.

beforeEach(function (): void {
    $this->fixture = HistorialTestData::fixture();
    $this->travelTo(CarbonImmutable::parse($this->fixture['today']));
    HistorialTestData::acumulacionRegla();
});

it('keeps the profile config equal to the fixture', function (): void {
    $avisos = collect($this->fixture['profile']['email_notices'])->pluck('label', 'evento')->all();

    expect(config('perfil.aviso_vencimiento_dias'))->toBe($this->fixture['history']['expiry_notice_days']['value'])
        ->and(PreferenciaAviso::interruptores())->toBe($avisos)
        ->and(array_intersect($this->fixture['profile']['always_sent'], array_keys($avisos)))->toBe([])
        ->and(EnvioNotificacion::PREFERENCIA_CLIENTE)->toBe($this->fixture['profile']['omitted_reason']);
});

it('shows the balance, the expiry notice and the rows of the window grouped by month', function (): void {
    $esperado = $this->fixture['expected_history'];
    $historial = app(HistorialCliente::class)->para(HistorialTestData::cliente());

    $meses = array_map(
        static fn (array $filas): array => array_map(static fn (FilaHistorial $fila): array => [
            'title' => $fila->titulo,
            'line' => $fila->linea,
            'points' => $fila->puntos,
            'color' => $fila->suma ? 'success-ink' : 'danger',
        ], $filas),
        $historial->meses,
    );
    $esperados = collect($esperado['months'])->mapWithKeys(static fn (array $mes): array => [
        $mes['label'] => array_map(
            static fn (array $fila): array => array_diff_key($fila, ['key' => true]),
            $mes['rows'],
        ),
    ])->all();

    expect($historial->titulo)->toBe($esperado['title'])
        ->and($historial->saldo)->toBe($esperado['saldo_puntos'])
        ->and(implode(' ', $historial->avisoVencimiento ?? []))->toBe($esperado['expiry_notice'])
        ->and($meses)->toBe($esperados);
});

it('matches the balance with the ledger', function (): void {
    $cliente = HistorialTestData::cliente();
    $check = app(RecalculateSaldo::class)($cliente->id);

    expect($check->matches())->toBeTrue()
        ->and(app(HistorialCliente::class)->para($cliente)->saldo)->toBe($check->calculated);
});

it('shows no expiry notice when no lot expires inside the window', function (): void {
    $caso = $this->fixture['expiry_notice_outside_window'];
    $this->travelTo(CarbonImmutable::parse($caso['today']));
    $antes = array_values(array_filter(
        $this->fixture['movements'],
        static fn (array $movimiento): bool => CarbonImmutable::parse($movimiento['at'])->lte(CarbonImmutable::now()),
    ));

    expect(app(HistorialCliente::class)->para(HistorialTestData::cliente($antes))->avisoVencimiento)
        ->toBe($caso['expected_notice']);
});

it('renders the history screen without forbidden words', function (): void {
    $this->actingAs(HistorialTestData::cliente(), 'cliente');
    $esperado = $this->fixture['expected_history'];

    $html = $this->get(route('cliente.historial'))
        ->assertOk()
        ->assertSee($esperado['title'])
        ->assertSee('917')
        ->assertSee('Noviembre de 2025')
        ->assertSee('−32')
        ->assertDontSee('Compra registrada a mano')
        ->assertSee('href="' . route('cliente.perfil') . '"', false)
        ->getContent();

    foreach ($esperado['forbidden_words'] as $palabra) {
        expect(mb_strtolower((string) $html))->not->toContain(mb_strtolower($palabra));
    }
});

it('shows the empty state to a customer without movements', function (): void {
    $this->actingAs(HistorialTestData::cliente([]), 'cliente');

    Livewire::test(Historial::class)
        ->assertSee($this->fixture['history']['empty_state']['title'])
        ->assertSee($this->fixture['history']['empty_state']['body']);
});

it('makes the same number of queries with 3 and with 30 movements', function (): void {
    $consultas = function (int $cantidad): int {
        $movimiento = $this->fixture['movements'][4];
        $movimientos = array_map(static fn (int $i): array => [
            ...$movimiento,
            'key' => "compra-{$i}",
            'at' => CarbonImmutable::parse('2026-10-01 10:00:00')->addMinutes($i)->toDateTimeString(),
        ], range(1, $cantidad));
        $cliente = HistorialTestData::cliente($movimientos, "99000{$cantidad}");

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(HistorialCliente::class)->para($cliente);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($consultas(30))->toBe($consultas(3));
});

it('shows the profile header, the masked document and the formatted phone', function (): void {
    $esperado = $this->fixture['expected_profile'];
    $this->actingAs(HistorialTestData::cliente(), 'cliente');

    $html = $this->get(route('cliente.perfil'))
        ->assertOk()
        ->assertSee($esperado['initials'])
        ->assertSee($esperado['level_line'])
        ->assertSee($esperado['document'])
        ->assertSee($esperado['phone'])
        ->assertSee($esperado['no_email_notice'])
        ->assertSee('Disponible pronto')
        ->assertSee('Cerrar sesión')
        ->getContent();

    foreach ($esperado['forbidden_words'] as $palabra) {
        expect((string) $html)->not->toContain($palabra);
    }
});

it('masks a short document completely', function (): void {
    $caso = $this->fixture['expected_profile']['short_document'];
    $cliente = HistorialTestData::cliente([]);
    $cliente->forceFill(['tipo_documento' => $caso['tipo_documento'], 'documento' => $caso['documento']])->save();
    $this->actingAs($cliente, 'cliente');

    Livewire::test(Perfil::class)->assertSee($caso['expected']);
});

it('saves the email normalized and rejects an invalid one', function (array $caso): void {
    $cliente = HistorialTestData::cliente([]);
    $this->actingAs($cliente, 'cliente');

    $componente = Livewire::test(Perfil::class)
        ->call('editar', 'email')
        ->set('email', $caso['input'])
        ->call('guardarCorreo');

    if (isset($caso['expected_error'])) {
        $componente->assertHasErrors($caso['expected_error'])->assertSet('editando', 'email');
        expect($cliente->refresh()->email)->toBeNull();

        return;
    }

    $componente->assertHasNoErrors()->assertSet('editando', null)->assertSee($caso['expected']);
    expect($cliente->refresh()->email)->toBe($caso['expected']);
})->with(fn (): array => collect(HistorialTestData::fixture()['email_cases'])
    ->keyBy('_description')->map(static fn (array $caso): array => [$caso])->all());

it('lets the customer add the birthdate only once', function (): void {
    $cliente = HistorialTestData::cliente([]);
    $this->actingAs($cliente, 'cliente');

    Livewire::test(Perfil::class)
        ->assertSee('Agregar')
        ->call('editar', 'fechaNacimiento')
        ->set('fechaNacimiento', '1990-03-14')
        ->call('guardarFecha')
        ->assertHasNoErrors()
        ->assertSee('14 de marzo de 1990')
        ->assertDontSeeHtml("wire:click=\"editar('fechaNacimiento')\"");

    expect($cliente->refresh()->fecha_nacimiento?->toDateString())->toBe('1990-03-14')
        ->and(static fn () => app(SetFechaNacimiento::class)($cliente, '1991-01-01'))
        ->toThrow(ValidationException::class, SetFechaNacimiento::YA_REGISTRADA);
    expect($cliente->refresh()->fecha_nacimiento?->toDateString())->toBe('1990-03-14');
});

it('rejects a birthdate in the future', function (): void {
    $this->actingAs(HistorialTestData::cliente([]), 'cliente');

    Livewire::test(Perfil::class)
        ->call('editar', 'fechaNacimiento')
        ->set('fechaNacimiento', '2030-01-01')
        ->call('guardarFecha')
        ->assertHasErrors('fechaNacimiento');
});

it('disables the email notices for a customer without email', function (): void {
    $this->actingAs(HistorialTestData::cliente([]), 'cliente');

    Livewire::test(Perfil::class)
        ->assertSee($this->fixture['expected_profile']['no_email_notice'])
        ->assertSeeHtml('aria-checked="false"')
        ->assertDontSeeHtml('aria-checked="true"')
        ->call('cambiarAviso', 'subida_nivel', false)
        ->assertHasErrors('avisos');

    expect(PreferenciaAviso::query()->count())->toBe(0);
});

it('turns a notice off and on, one row per customer and event', function (): void {
    $cliente = HistorialTestData::cliente([]);
    $cliente->forceFill(['email' => 'marcela@example.com'])->save();
    $this->actingAs($cliente, 'cliente');

    Livewire::test(Perfil::class)
        ->assertDontSeeHtml('aria-checked="false"')
        ->call('cambiarAviso', 'subida_nivel', false)
        ->assertHasNoErrors()
        ->call('cambiarAviso', 'subida_nivel', true)
        ->call('cambiarAviso', 'subida_nivel', false)
        ->assertSeeHtml('aria-checked="false"');

    expect(PreferenciaAviso::query()->count())->toBe(1)
        ->and(PreferenciaAviso::correoApagado($cliente->id, App\Enums\EventoNotificacion::SubidaNivel))->toBeTrue();
});

it('rejects a notice without a switch', function (string $evento): void {
    $cliente = HistorialTestData::cliente([]);
    $cliente->forceFill(['email' => 'marcela@example.com'])->save();

    expect(static fn () => app(SetPreferenciaAviso::class)($cliente, $evento, false))
        ->toThrow(ValidationException::class);
})->with(fn (): array => HistorialTestData::fixture()['profile']['always_sent']);

it('skips the level-up email the customer turned off but keeps the app notice', function (bool $encendido): void {
    Mail::fake();
    NivelesTestData::nivelesRegla();
    $cliente = NivelesTestData::cliente(['nivel_por_puntos' => 'bronce', 'saldo_puntos' => 600]);
    $cliente->forceFill(['email' => 'ana@example.com'])->save();
    app(SetPreferenciaAviso::class)($cliente, 'subida_nivel', $encendido);

    app(RaiseNivelEfectivo::class)($cliente->id, MotivoCambioNivel::AscensoCompra);

    $envios = EnvioNotificacion::query()->where('evento', 'subida_nivel')->get()->keyBy(
        static fn (EnvioNotificacion $envio): string => $envio->canal->value,
    );
    expect($envios['app']->estado)->toBe(EstadoEnvio::Enviado)
        ->and($envios['correo']->estado)->toBe($encendido ? EstadoEnvio::Enviado : EstadoEnvio::Omitido)
        ->and($envios['correo']->motivo)->toBe($encendido ? null : $this->fixture['profile']['omitted_reason']);
})->with(['switched off' => false, 'switched on' => true]);

it('links the documents only when their address is configured', function (): void {
    $this->actingAs(HistorialTestData::cliente([]), 'cliente');
    config(['perfil.reglamento_url' => 'https://example.com/reglamento.pdf']);

    Livewire::test(Perfil::class)
        ->assertSeeHtml('href="https://example.com/reglamento.pdf"')
        ->assertSee('Disponible pronto');

    config(['perfil.politica_datos_url' => 'https://example.com/politica.pdf']);
    $this->app['auth']->guard('cliente')->logout();
    $this->get(route('cliente.registro'))->assertSee('href="https://example.com/politica.pdf"', false);
});

it('sends a guest to the login page', function (string $ruta): void {
    $this->get(route($ruta))->assertRedirect(route('cliente.ingreso'));
})->with(['cliente.historial', 'cliente.perfil']);

it('moves the logout from the home screen to the profile', function (): void {
    NivelesTestData::nivelesRegla();
    $this->actingAs(HistorialTestData::cliente([]), 'cliente');

    $this->get(route('cliente.inicio'))
        ->assertOk()
        ->assertDontSee('Cerrar sesión')
        ->assertSee('href="' . route('cliente.perfil') . '"', false)
        ->assertSee('href="' . route('cliente.historial') . '"', false);
});
