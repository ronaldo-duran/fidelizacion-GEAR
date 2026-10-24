<?php

declare(strict_types=1);

use App\Actions\Clientes\LoginCliente;
use App\Actions\Clientes\MakeEnlaceRegistro;
use App\Actions\Clientes\RequestPinReset;
use App\Actions\Clientes\ResetPin;
use App\Actions\Clientes\StartRegistro;
use App\Enums\CanalRegistro;
use App\Enums\EstadoTransaccion;
use App\Enums\TipoDocumento;
use App\Jobs\Nocturno\PurgeRegistrosPendientes;
use App\Livewire\Cliente\Registro;
use App\Livewire\Cliente\VerificarRegistro;
use App\Models\AutorizacionDatos;
use App\Models\Cliente;
use App\Models\RegistroPendiente;
use App\Models\Transaccion;
use App\Models\User;
use App\Sms\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\ComprasTestData;

beforeEach(function (): void {
    $this->fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/registro-escenarios.json'), true);
    $this->travelTo(CarbonImmutable::parse('2026-10-24 10:00:00'));
    $this->sms = app(SmsSender::class);
    $this->case = function (string $criterion): array {
        foreach ($this->fixture['cases'] as $case) {
            if ($case['_e8_02_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    };
    // "hoy-30y" del fixture: hace 30 años.
    $this->fecha = static function (string $relativa): string {
        preg_match('/^hoy-(\d+)y$/', $relativa, $partes);

        return CarbonImmutable::today()->subYears((int) $partes[1])->toDateString();
    };
    // Los campos del fixture van en snake_case; los del formulario, en camelCase.
    $this->llenar = function (array $form) {
        $component = Livewire::test(Registro::class);

        foreach ($form as $campo => $valor) {
            $valor = $campo === 'fecha_nacimiento' ? ($this->fecha)($valor) : $valor;
            $component->set('form.' . Illuminate\Support\Str::camel($campo), $valor);
        }

        return $component;
    };
    $this->ultimoCodigo = function (): string {
        $enviados = $this->sms->enviados();
        preg_match('/\b(\d{6})\b/', end($enviados)['mensaje'] ?? '', $match);

        return $match[1] ?? '';
    };
    // Registro completo hasta la verificación; devuelve el componente de verificar ya con el código puesto.
    $this->registrar = function (array $form) {
        ($this->llenar)($form)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('cliente.registro.verificar'));

        return Livewire::test(VerificarRegistro::class)->set('codigo', ($this->ultimoCodigo)());
    };
    $this->clienteCon = static function (array $datos): Cliente {
        $cliente = new Cliente([
            'tipo_documento' => TipoDocumento::from($datos['tipo_documento']),
            'documento' => $datos['documento'],
            'celular' => $datos['celular'] ?? '3009998877',
        ]);
        $cliente->forceFill([
            'nombre_completo' => 'Cliente de prueba',
            'pin_hash' => Hash::make($datos['pin'] ?? '4826'),
            'celular_verificado_at' => CarbonImmutable::now(),
        ])->save();

        return $cliente;
    };
});

it('keeps the validation rules and limits in config equal to the fixture', function (): void {
    $validation = $this->fixture['validation'];
    $limits = $this->fixture['limits'];

    expect(config('registro.documento_patterns'))
        ->toBe(array_map(static fn (array $tipo): string => $tipo['pattern'], $validation['documento']))
        ->and(config('registro.celular_pattern'))->toBe($validation['celular']['pattern'])
        ->and(config('registro.pin_digits'))->toBe($validation['pin']['digits'])
        ->and(config('registro.edad_minima_anios'))->toBe($validation['edad_minima_anios']['value'])
        ->and(config('registro.nombre_min_length'))->toBe($validation['nombre_completo']['min_length'])
        ->and(config('registro.nombre_max_length'))->toBe($validation['nombre_completo']['max_length'])
        ->and(config('registro.login_max_attempts'))->toBe($limits['login_max_attempts']['value'])
        ->and(config('registro.login_lockout_minutes'))->toBe($limits['login_lockout_minutes']['value'])
        ->and(config('registro.registro_pendiente_minutes'))->toBe($limits['registro_pendiente_minutes']['value'])
        ->and(config('registro.enlace_caja_minutes'))->toBe($limits['enlace_caja_minutes']['value']);
});

it('creates the customer with its authorization and session once the phone is verified', function (): void {
    $case = ($this->case)('a verified registration creates the customer with its authorization');

    ($this->registrar)($case['form'])->call('verify')->assertHasNoErrors()->assertRedirect(route('cliente.inicio'));

    $cliente = Cliente::query()->where('documento', $case['form']['documento'])->sole();
    $autorizacion = AutorizacionDatos::query()->where('cliente_id', $cliente->id)->sole();

    expect($cliente->nombre_completo)->toBe($case['form']['nombre_completo'])
        ->and($cliente->celular_verificado_at)->not->toBeNull()
        ->and(Hash::check($case['form']['pin'], (string) $cliente->pin_hash))->toBeTrue()
        ->and($autorizacion->version_texto)->toBe(config('registro.autorizacion_version'))
        ->and($autorizacion->canal)->toBe(CanalRegistro::Pwa)
        ->and($autorizacion->ip)->not->toBeNull()
        ->and($autorizacion->aceptada_at)->not->toBeNull()
        ->and(Auth::guard('cliente')->id())->toBe($cliente->id)
        ->and(RegistroPendiente::query()->count())->toBe(0);
});

it('creates nothing without the authorization', function (): void {
    $case = ($this->case)('no account without the authorization');

    ($this->llenar)($case['form'])->call('submit')->assertHasErrors(['form.' . $case['expected']['error_field']]);

    expect($this->sms->enviados())->toBe([])
        ->and(RegistroPendiente::query()->count())->toBe(0)
        ->and(Cliente::query()->count())->toBe(0);
});

it('rejects invalid fields before sending the SMS', function (): void {
    $valido = ($this->case)('a verified registration creates the customer with its authorization')['form'];
    $case = ($this->case)('invalid fields are rejected before sending the SMS');

    foreach ($case['attempts'] as $attempt) {
        $form = [...$valido, $attempt['field'] => $attempt['value']];

        if (isset($attempt['tipo_documento'])) {
            $form['tipo_documento'] = $attempt['tipo_documento'];
        }

        if ($attempt['field'] === 'pin') {
            $form['pin_confirmation'] = $attempt['value'];
        }

        ($this->llenar)($form)
            ->call('submit')
            ->assertHasErrors(['form.' . Illuminate\Support\Str::camel($attempt['field'])]);
    }

    expect($this->sms->enviados())->toBe([])
        ->and(RegistroPendiente::query()->count())->toBe(0);
});

it('rejects the descending PIN and a confirmation that does not match', function (): void {
    $valido = ($this->case)('a verified registration creates the customer with its authorization')['form'];

    ($this->llenar)([...$valido, 'pin' => '4321', 'pin_confirmation' => '4321'])
        ->call('submit')
        ->assertHasErrors(['form.pin']);
    ($this->llenar)([...$valido, 'pin_confirmation' => '9999'])
        ->call('submit')
        ->assertHasErrors(['form.pinConfirmation']);

    expect($this->sms->enviados())->toBe([]);
});

it('keeps an unverified registration out: no customer, purchases still pending and no access', function (): void {
    $case = ($this->case)('an unverified registration creates nothing');
    $data = ComprasTestData::seed();
    $compra = $data->pendiente([
        ...$case['pending_compras'][0],
        'comercio_id' => 2,
        'referencia_externa' => 'caja-e802-1',
        'created_at' => 'hoy-3d',
    ]);

    ($this->llenar)($case['form'])->call('submit')->assertHasNoErrors();

    expect(Cliente::query()->count())->toBe(0)
        ->and($compra->fresh()?->estado)->toBe(EstadoTransaccion::Pendiente)
        ->and(fn () => app(LoginCliente::class)(TipoDocumento::PPT, 'PPT5566', '9134'))
        ->toThrow(ValidationException::class, LoginCliente::NO_COINCIDEN);
});

it('credits on verification only the pending purchases of that document type and number', function (): void {
    $case = ($this->case)('pending purchases are credited on verification');
    $data = ComprasTestData::seed();
    $compras = [];

    foreach ($case['pending_compras'] as $i => $pendiente) {
        $compras[] = $data->pendiente([
            ...$pendiente,
            'comercio_id' => 2,
            'referencia_externa' => "caja-e802-{$i}",
            'created_at' => 'hoy-3d',
        ]);
    }

    ($this->registrar)($case['form'])->call('verify')->assertHasNoErrors();

    $cliente = Cliente::query()->where('tipo_documento', 'PPT')->sole();
    $acreditadas = Transaccion::query()->where('estado', EstadoTransaccion::Acreditada->value)->get();

    expect($acreditadas)->toHaveCount($case['expected']['compras_acreditadas'])
        ->and($acreditadas->first()?->id)->toBe($compras[0]->id)
        ->and($acreditadas->first()?->cliente_id)->toBe($cliente->id)
        ->and(Transaccion::query()->where('estado', EstadoTransaccion::Pendiente->value)->count())
        ->toBe($case['expected']['compras_pendientes_restantes'])
        ->and($cliente->fresh()?->saldo_puntos)->toBeGreaterThan(0);
});

it('answers a duplicate document with a message that reveals nothing', function (): void {
    $case = ($this->case)('a duplicate document reveals nothing');
    $valido = ($this->case)('a verified registration creates the customer with its authorization')['form'];
    ($this->clienteCon)($case['existing_cliente']);

    ($this->llenar)([...$valido, ...$case['form']])
        ->call('submit')
        ->assertHasErrors(['form.documento'])
        ->assertSee($case['expected']['message']);

    expect($this->sms->enviados())->toBe([])
        ->and(StartRegistro::DOCUMENTO_REGISTRADO)->toBe($case['expected']['message']);
});

it('opens the registration from the cashier link prefilled and rejects it expired or altered', function (): void {
    $case = ($this->case)('the cashier link prefills the document and expires');
    $tipo = TipoDocumento::from($case['link']['tipo_documento']);
    $enlace = app(MakeEnlaceRegistro::class)($tipo, $case['link']['documento']);

    $this->get($enlace)->assertRedirect(route('cliente.registro'))->assertSessionHas('registro.prefill');

    Livewire::test(Registro::class)
        ->assertSet('form.tipoDocumento', $case['link']['tipo_documento'])
        ->assertSet('form.documento', $case['link']['documento'])
        ->assertSet('canal', CanalRegistro::CajaQr->value);

    $this->get(str_replace('E123456', 'E999999', $enlace))->assertForbidden();
    $this->travel($this->fixture['limits'][$case['expected']['expired_after_minutes_from']]['value'] + 1)->minutes();
    $this->get($enlace)->assertForbidden();
});

it('logs in with the PIN, hides whether the document exists and locks after the maximum attempts', function (): void {
    $case = ($this->case)('login with PIN and lockout');
    ($this->clienteCon)($case['existing_cliente']);
    $login = static fn (string $documento, string $pin): mixed => app(LoginCliente::class)(
        TipoDocumento::CC,
        $documento,
        $pin,
    );

    expect(fn () => $login('99999999', '1357'))
        ->toThrow(ValidationException::class, $case['expected']['error_message']);

    foreach ($case['attempts'] as $i => $pin) {
        $intento = $i + 1;
        $esperado = $intento >= $case['expected']['locked_on_attempt']
            ? 'Hiciste demasiados intentos'
            : $case['expected']['error_message'];

        expect(fn () => $login('1023 456 789', $pin))->toThrow(ValidationException::class, $esperado);
    }

    $this->travel($this->fixture['limits']['login_lockout_minutes']['value'])->minutes();
    $this->travel(1)->seconds();

    expect($login('1023456789', $case['existing_cliente']['pin'])->documento)->toBe('1023456789');
});

it('resets the PIN with the SMS code and closes the other sessions', function (): void {
    $case = ($this->case)('reset the PIN with the SMS code');
    $cliente = ($this->clienteCon)($case['existing_cliente']);
    $hashAnterior = (string) $cliente->pin_hash;

    expect(app(RequestPinReset::class)(TipoDocumento::CC, '1023456789'))->toBe(RequestPinReset::RESPUESTA);

    app(ResetPin::class)(TipoDocumento::CC, '1023456789', ($this->ultimoCodigo)(), $case['new_pin']);
    $login = static fn (string $pin): mixed => app(LoginCliente::class)(TipoDocumento::CC, '1023456789', $pin);

    expect(fn () => $login($case['existing_cliente']['pin']))->toThrow(ValidationException::class)
        ->and($login($case['new_pin'])->id)->toBe($cliente->id);

    // Otra sesión abierta antes del cambio: guarda el hash anterior y queda afuera.
    $this->actingAs($cliente->fresh() ?? $cliente, 'cliente')
        ->withSession(['password_hash_cliente' => $hashAnterior])
        ->get(route('cliente.inicio'))
        ->assertRedirect(route('cliente.ingreso'));
});

it('answers the PIN reset the same whether the document exists or not', function (): void {
    ($this->clienteCon)(['tipo_documento' => 'CC', 'documento' => '1023456789', 'celular' => '3001234567']);

    expect(app(RequestPinReset::class)(TipoDocumento::CC, '55555555'))->toBe(RequestPinReset::RESPUESTA)
        ->and($this->sms->enviados())->toBe([])
        ->and(app(RequestPinReset::class)(TipoDocumento::CE, '1023456789'))->toBe(RequestPinReset::RESPUESTA)
        ->and($this->sms->enviados())->toBe([])
        ->and(app(RequestPinReset::class)(TipoDocumento::CC, '1023456789'))->toBe(RequestPinReset::RESPUESTA)
        ->and($this->sms->enviados())->toHaveCount(1)
        ->and(app(RequestPinReset::class)(TipoDocumento::CC, '1023456789'))->toBe(RequestPinReset::RESPUESTA);

    expect(fn () => app(ResetPin::class)(TipoDocumento::CC, '55555555', '123456', '7351'))
        ->toThrow(ValidationException::class);
});

it('does not let a customer into the panel', function (): void {
    $cliente = ($this->clienteCon)(['tipo_documento' => 'CC', 'documento' => '1023456789']);

    $this->actingAs($cliente, 'cliente')->get('/admin')->assertRedirect('/admin/login');
});

it('gives a panel user no customer session', function (): void {
    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('cliente.inicio'))
        ->assertRedirect(route('cliente.ingreso'));
});

it('refuses an expired pending registration', function (): void {
    $case = ($this->case)('a verified registration creates the customer with its authorization');
    $verificar = ($this->registrar)($case['form']);

    $this->travel($this->fixture['limits']['registro_pendiente_minutes']['value'] + 1)->minutes();

    $verificar->call('verify')->assertHasErrors(['codigo']);

    expect(Cliente::query()->count())->toBe(0);
});

it('purges expired pending registrations at night', function (): void {
    $case = ($this->case)('a verified registration creates the customer with its authorization');
    ($this->llenar)($case['form'])->call('submit');
    $this->travelTo(CarbonImmutable::parse('2026-10-25 01:30:00'));

    Bus::dispatchSync(new PurgeRegistrosPendientes('2026-10-25'));

    expect(RegistroPendiente::query()->count())->toBe(0);
});
