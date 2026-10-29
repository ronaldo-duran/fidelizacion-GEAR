<?php

declare(strict_types=1);

use App\Actions\Cajeros\FindCliente;
use App\Actions\Cajeros\LoginCajero;
use App\Actions\Compras\AnnulCompra;
use App\Actions\Compras\RegisterCompra;
use App\Actions\Credenciales\IssueCredencial;
use App\Compras\NuevaCompra;
use App\Enums\CanalCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\PropositoCredencial;
use App\Enums\TipoDocumento;
use App\Enums\TipoMovimiento;
use App\Livewire\Cajero\Caja;
use App\Livewire\Cajero\Ingreso;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CajeroTestData;
use Tests\Support\ComprasTestData;

beforeEach(function (): void {
    $this->fixture = CajeroTestData::fixture();
    $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00:00'));
    // La heladería no tiene bono: 1 punto por $1.000.
    $this->datos = ComprasTestData::seed();
    $this->comercioId = $this->datos->comercios[2];
    $this->sedeId = $this->datos->sedes[21];
    $this->cajero = User::factory()->cajero($this->comercioId, $this->sedeId)->create();
    $this->case = static fn (string $criterion): array => CajeroTestData::fixtureCase($criterion);
    $this->cliente = function (string $tipo, string $documento): Cliente {
        $cliente = Cliente::query()->create([
            'tipo_documento' => TipoDocumento::from($tipo),
            'documento' => $documento,
        ]);
        $cliente->forceFill(['nombre_completo' => 'Marcela Gómez'])->save();

        return $cliente;
    };
    // La caja hasta el paso de confirmar, con el monto y el documento del caso.
    $this->identificar = fn (int $monto, string $tipo, string $documento) => Livewire::actingAs($this->cajero)
        ->test(Caja::class)
        ->call('continuar', $monto)
        ->set('form.tipoDocumento', $tipo)
        ->set('form.documento', $documento)
        ->call('buscar');
});

it('keeps the cashier settings in config equal to the fixture', function (): void {
    $settings = $this->fixture['settings'];

    expect(config('cajero.max_amount_digits'))->toBe($settings['max_amount_digits']['value'])
        ->and(config('cajero.auto_return_seconds'))->toBe($settings['auto_return_seconds']['value'])
        ->and(config('cajero.annul_hours'))->toBe($settings['cashier_annul_hours']['value'])
        ->and(config('cajero.annul_reason'))->toBe($settings['cashier_annul_reason'])
        ->and(config('cajero.login_max_attempts'))->toBe($settings['login_max_attempts']['value'])
        ->and(config('cajero.login_lockout_minutes'))->toBe($settings['login_lockout_minutes']['value'])
        ->and(config('cajero.login_max_attempts_per_ip'))->toBe($settings['login_max_attempts_per_ip']['value'])
        ->and(config('cajero.search_max_per_minute'))->toBe($settings['search_max_per_minute']['value']);
});

it('lets in only active cashiers with a branch, with the same message for everyone else', function (
    ?string $rol,
    bool $activo,
    bool $sede,
    string $expected,
): void {
    $email = 'caja@bbc.co';

    if ($rol !== null) {
        User::factory()->create([
            'email' => $email,
            'rol' => $rol,
            'activo' => $activo,
            'comercio_id' => $this->comercioId,
            'sede_id' => $sede ? $this->sedeId : null,
        ]);
    }

    $ingreso = Livewire::test(Ingreso::class)
        ->set('email', $email)
        ->set('password', 'password')
        ->call('submit');

    if ($expected === 'in') {
        $ingreso->assertRedirect(route('caja.inicio'));
        expect(auth()->user()?->email)->toBe($email);

        return;
    }

    $ingreso->assertHasErrors(['email'])->assertSee(LoginCajero::NO_COINCIDEN);
    expect(auth()->check())->toBeFalse();
})->with(array_map(
    static fn (array $login): array => [
        $login['rol'],
        $login['activo'] ?? false,
        $login['sede'] ?? false,
        $login['expected'],
    ],
    CajeroTestData::fixtureCase('only active cashiers with a branch log in')['logins'],
));

it('rejects a wrong password with the same message and locks the email after too many tries', function (): void {
    $intentar = fn (string $password) => Livewire::test(Ingreso::class)
        ->set('email', $this->cajero->email)
        ->set('password', $password)
        ->call('submit');

    $intentar('otra')->assertSee(LoginCajero::NO_COINCIDEN);

    foreach (range(2, config('cajero.login_max_attempts')) as $ignored) {
        $intentar('otra');
    }

    $intentar('password')->assertHasErrors(['email'])->assertSee('Hiciste demasiados intentos');
    expect(auth()->check())->toBeFalse();
});

it('keeps the email lockout to the IP that failed, so nobody locks out another cashier', function (): void {
    $login = app(LoginCajero::class);

    foreach (range(1, config('cajero.login_max_attempts')) as $ignored) {
        expect(fn () => $login($this->cajero->email, 'otra', '10.0.0.1'))->toThrow(ValidationException::class);
    }

    expect(fn () => $login($this->cajero->email, 'password', '10.0.0.1'))
        ->toThrow(ValidationException::class, 'Hiciste demasiados intentos')
        ->and($login($this->cajero->email, 'password', '10.0.0.2')->id)->toBe($this->cajero->id);
});

it('stops an IP that fails with too many different emails', function (): void {
    $login = app(LoginCajero::class);

    foreach (range(1, config('cajero.login_max_attempts_per_ip')) as $intento) {
        expect(fn () => $login("nadie{$intento}@example.com", 'otra', '10.0.0.1'))
            ->toThrow(ValidationException::class, LoginCajero::NO_COINCIDEN);
    }

    expect(fn () => $login($this->cajero->email, 'password', '10.0.0.1'))
        ->toThrow(ValidationException::class, 'Hiciste demasiados intentos')
        ->and($login($this->cajero->email, 'password', '10.0.0.2')->id)->toBe($this->cajero->id);
});

it('closes the session of a cashier deactivated while logged in', function (): void {
    $this->actingAs($this->cajero)->get(route('caja.inicio'))->assertOk()->assertSee('Monto de la compra');

    $this->cajero->forceFill(['activo' => false])->save();

    $this->get(route('caja.inicio'))->assertRedirect(route('caja.ingreso'));
    $this->assertGuest();
});

it('sends guests to the cashier login and keeps other roles out of the cash register', function (): void {
    $this->get(route('caja.inicio'))->assertRedirect(route('caja.ingreso'));

    $admin = User::factory()->administradorDeComercio($this->comercioId)->create();

    $this->actingAs($admin)->get(route('caja.inicio'))->assertForbidden();
    $this->assertAuthenticatedAs($admin);
});

it('validates the amount on the server: more than zero and within the maximum digits', function (int $monto): void {
    Livewire::actingAs($this->cajero)
        ->test(Caja::class)
        ->call('continuar', $monto)
        ->assertHasErrors(['monto'])
        ->assertSet('paso', 'monto');
})->with([
    'zero' => [0],
    'ten digits' => [1_000_000_000],
]);

it('registers a registered customer in the cashier branch and shows the points', function (): void {
    $case = ($this->case)('a registered customer is registered from the cashier');
    ($this->cliente)($case['cliente']['tipo_documento'], $case['cliente']['documento']);

    $caja = ($this->identificar)($case['amount'], ...array_values($case['identify_by']))
        ->assertSet('paso', 'confirmar')
        ->assertSee('Marcela Gómez')
        ->call('registrar')
        ->assertSet('paso', $case['expected']['screen'])
        ->assertSee('Compra registrada')
        ->assertSee('+85')
        ->assertSee('Le faltan 415 para Plata')
        ->assertSee('Vuelve sola en ' . config('cajero.auto_return_seconds') . ' s');

    $transaccion = Transaccion::query()->sole();

    expect($transaccion->estado->value)->toBe($case['expected']['estado'])
        ->and($transaccion->comercio_id)->toBe($this->comercioId)
        ->and($transaccion->sede_id)->toBe($this->sedeId)
        ->and($transaccion->canal)->toBe(CanalCompra::Cajero)
        ->and($transaccion->registrada_por)->toBe($this->cajero->id)
        ->and($caja->get('puntos'))->toBe(85);

    $caja->call('nuevaCompra')->assertSet('paso', 'monto')->assertSet('monto', 0);
});

it('does not show the points it adds on the amount screen', function (): void {
    Livewire::actingAs($this->cajero)
        ->test(Caja::class)
        ->assertSee('Monto de la compra')
        ->assertDontSee('Suma')
        ->assertDontSee('pts');
});

it('leaves a single purchase after a double tap on register', function (): void {
    $case = ($this->case)('a double tap registers once');
    ($this->cliente)($case['cliente']['tipo_documento'], $case['cliente']['documento']);

    $caja = ($this->identificar)($case['amount'], 'CC', $case['cliente']['documento']);

    foreach (range(1, $case['taps']) as $ignored) {
        $caja->call('registrar');
    }

    expect(Transaccion::query()->count())->toBe($case['expected']['purchases']);
});

it('returns the same purchase when the same attempt reaches the server twice', function (): void {
    ($this->cliente)('CC', '1023456789');
    $caja = ($this->identificar)(85000, 'CC', '1023456789');
    $compra = new NuevaCompra(
        $this->comercioId,
        $this->sedeId,
        TipoDocumento::CC,
        '1023456789',
        85000,
        $caja->get('referencia'),
        CanalCompra::Cajero,
    );

    $caja->call('registrar');
    $repetida = app(RegisterCompra::class)($this->cajero, $compra);

    expect($repetida->repetida)->toBeTrue()
        ->and(Transaccion::query()->count())->toBe(1)
        ->and($repetida->transaccion->puntosOtorgados())->toBe(85);
});

it('identifies the customer with a valid identify QR and rejects others without customer data', function (): void {
    $case = ($this->case)('the customer QR identifies or is rejected');
    $cliente = ($this->cliente)($case['cliente']['tipo_documento'], $case['cliente']['documento']);

    foreach ($case['credentials'] as $credential) {
        $proposito = PropositoCredencial::from($credential['purpose']);
        $emitida = app(IssueCredencial::class)(
            $cliente,
            $proposito,
            $proposito === PropositoCredencial::Beneficio ? 7 : null,
            $proposito === PropositoCredencial::Beneficio ? $this->comercioId : null,
        );
        $this->travel($credential['age_seconds'])->seconds();

        $caja = Livewire::actingAs($this->cajero)
            ->test(Caja::class)
            ->call('continuar', 85000)
            ->set('metodo', 'qr')
            ->call('identificarQr', $emitida->token);

        if ($credential['expected'] === 'identified') {
            $caja->assertSet('paso', 'confirmar')->assertSet('clienteId', $cliente->id)->assertSee('Marcela Gómez');
        } else {
            $caja->assertSet('paso', 'identificar')
                ->assertSet('clienteId', null)
                ->assertHasErrors(['form.codigo'])
                ->assertDontSee('Marcela Gómez')
                ->assertDontSee($cliente->documento);
        }

        $this->travelBack();
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00:00'));
    }
});

it('identifies the customer with the short code typed by the cashier', function (): void {
    $cliente = ($this->cliente)('CC', '1023456789');
    $emitida = app(IssueCredencial::class)($cliente, PropositoCredencial::Identificar);

    Livewire::actingAs($this->cajero)
        ->test(Caja::class)
        ->call('continuar', 85000)
        ->set('metodo', 'qr')
        ->set('form.codigo', $emitida->credencial->codigo_corto)
        ->call('identificarQr')
        ->assertSet('paso', 'confirmar')
        ->assertSet('clienteId', $cliente->id);
});

it('keeps the amount for an unknown document and each exit does what the design says', function (): void {
    $case = ($this->case)('an unknown document keeps the amount');

    foreach ($case['exits'] as $exit) {
        Transaccion::query()->toBase()->delete();
        $caja = ($this->identificar)($case['amount'], ...array_values($case['identify_by']))
            ->assertSet('paso', 'no_registrado')
            ->assertSet('monto', $case['amount'])
            ->assertSee('No está registrada')
            ->assertSee('Guardado');

        match ($exit['exit']) {
            'guardar_pendiente' => $caja->call('guardarPendiente')->assertSee('Compra pendiente guardada'),
            'registrar_ahora' => $caja->call('registrarAhora')->assertSee('Que el cliente escanee'),
            default => $caja->call('volverA', 'identificar'),
        };

        $caja->assertSet('paso', $exit['expected']['screen']);

        if (isset($exit['expected']['purchases'])) {
            expect(Transaccion::query()->count())->toBe($exit['expected']['purchases']);
            $caja->assertSet('monto', $exit['expected']['amount_kept'])
                ->assertSet('form.documento', $case['identify_by']['documento']);

            continue;
        }

        $transaccion = Transaccion::query()->sole();
        expect($transaccion->estado->value)->toBe($exit['expected']['estado'])
            ->and($transaccion->cliente_id)->toBeNull()
            ->and($transaccion->sede_id)->toBe($this->sedeId);

        if (isset($exit['expected']['link_document'])) {
            expect($caja->get('enlace'))->toContain('documento=' . $exit['expected']['link_document'])
                ->and($caja->get('enlace'))->toContain('signature=');
        }
    }
});

it('limits the document searches of each cashier per minute', function (): void {
    $caja = Livewire::actingAs($this->cajero)->test(Caja::class)->call('continuar', 85000);
    $buscar = fn (string $documento) => $caja
        ->call('volverA', 'identificar')
        ->set('form.documento', $documento)
        ->call('buscar');

    foreach (range(1, config('cajero.search_max_per_minute')) as $intento) {
        $buscar((string) (1000000 + $intento))->assertSet('paso', 'no_registrado');
    }

    $buscar('1000999')
        ->assertSet('paso', 'identificar')
        ->assertHasErrors(['form.documento'])
        ->assertSee(FindCliente::DEMASIADAS);

    $this->travel(61)->seconds();

    $buscar('1000999')->assertSet('paso', 'no_registrado');
});

it('lets the cashier annul only their own purchase within the window', function (): void {
    $case = ($this->case)('the cashier annuls only their own purchase in time');
    ($this->cliente)($case['cliente']['tipo_documento'], $case['cliente']['documento']);
    $otroCajero = User::factory()->cajero($this->comercioId, $this->sedeId)->create();

    foreach ($case['annul_attempts'] as $attempt) {
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00:00'));
        $registro = app(RegisterCompra::class)($this->cajero, new NuevaCompra(
            $this->comercioId,
            $this->sedeId,
            TipoDocumento::CC,
            $case['cliente']['documento'],
            $case['amount'],
            'ref-' . fake()->unique()->numerify('######'),
            CanalCompra::Cajero,
        ));
        $this->travel($attempt['after_hours'])->hours();
        $quien = $attempt['by'] === 'same_cashier' ? $this->cajero : $otroCajero;
        $anular = fn (): Transaccion => app(AnnulCompra::class)(
            $quien,
            $registro->transaccion->id,
            config('cajero.annul_reason'),
        );

        if ($attempt['expected'] === 'rejected') {
            expect($anular)->toThrow(AuthorizationException::class);

            continue;
        }

        expect($anular()->estado)->toBe(EstadoTransaccion::Anulada)
            ->and(MovimientoPuntos::query()
                ->where('transaccion_id', $registro->transaccion->id)
                ->where('tipo', TipoMovimiento::Reversa->value)
                ->get()
                ->sum(static fn (MovimientoPuntos $movimiento): int => $movimiento->puntos))->toBe(-85);
    }
});

it('annuls from the result screen and offers the last purchase on the amount screen', function (): void {
    ($this->cliente)('CC', '1023456789');
    $caja = ($this->identificar)(85000, 'CC', '1023456789')->call('registrar');
    $id = $caja->get('transaccionId');

    $caja->call('nuevaCompra')->assertSee('Anular la última compra ($85.000)');
    $caja->call('anular', $id)->assertSet('paso', 'monto')->assertSee('Compra anulada.')
        ->assertDontSee('Anular la última compra');

    expect(Transaccion::query()->findOrFail($id)->estado)->toBe(EstadoTransaccion::Anulada);

    $this->travel(73)->hours();
    $otra = ($this->identificar)(50000, 'CC', '1023456789')->call('registrar');
    $this->travel(73)->hours();

    $otra->call('anular', $otra->get('transaccionId'))
        ->assertSee('Esta compra ya no se puede anular desde la caja');
});

it('never registers in another branch, even through the action', function (): void {
    $otraSede = $this->datos->sedes[11];
    $compra = new NuevaCompra(
        $this->datos->comercios[1],
        $otraSede,
        TipoDocumento::CC,
        '1023456789',
        85000,
        'ref-otra-sede',
        CanalCompra::Cajero,
    );

    expect(fn () => app(RegisterCompra::class)($this->cajero, $compra))->toThrow(AuthorizationException::class);

    ($this->identificar)(85000, 'CC', '1023456789')->call('guardarPendiente');

    expect(Transaccion::query()->pluck('sede_id')->all())->toBe([$this->sedeId]);
});
