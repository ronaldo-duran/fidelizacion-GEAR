<?php

declare(strict_types=1);

use App\Actions\Beneficios\CheckBeneficioCredencial;
use App\Actions\Beneficios\RedeemBeneficio;
use App\Actions\Cajeros\FindCliente;
use App\Actions\Credenciales\IssueCredencial;
use App\Beneficios\BeneficioParaCliente;
use App\Beneficios\BeneficiosParaCliente;
use App\Beneficios\EntregaRechazada;
use App\Beneficios\RevisionBeneficio;
use App\Enums\PropositoCredencial;
use App\Enums\TipoDocumento;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\CredencialQr;
use App\Models\MovimientoPuntos;
use App\Models\Redencion;
use App\Models\User;
use App\Support\FormatoColombiano;
use App\Tablero\AlcanceTablero;
use App\Tablero\BeneficiosDelPeriodo;
use App\Tablero\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BeneficiosTestData;
use Tests\Support\RedencionTestData;

beforeEach(function (): void {
    $this->fixture = RedencionTestData::fixture();
    $this->travelTo(CarbonImmutable::parse($this->fixture['today']));
    RedencionTestData::seed();
    $this->cajeros = [];
    $this->cajero = function (string $key): User {
        $nit = collect($this->fixture['cashiers'])->firstWhere('key', $key)['comercio_nit'];

        return $this->cajeros[$key] ??= RedencionTestData::cajero($nit);
    };
    $this->clientes = [];
    $this->cliente = fn (string $key): Cliente => $this->clientes[$key] ??= RedencionTestData::cliente(
        collect($this->fixture['customers'])->firstWhere('key', $key),
    );
    $this->rechazo = static function (callable $entrega): ?string {
        try {
            $entrega();
        } catch (EntregaRechazada $rechazo) {
            return $rechazo->chequeo->resultado->value;
        }

        return null;
    };
});

it('keeps the delivery settings in config equal to the fixture', function (): void {
    $settings = $this->fixture['settings'];

    expect(config('cajero.redeem_confirm_window_seconds'))->toBe($settings['confirm_window_seconds']['value'])
        ->and(config('cajero.support_max_length'))->toBe($settings['support_max_length']['value'])
        ->and($settings['granted_value_rounding']['value'])->toBe('floor');
});

it('grants the value of each fixture case, rounded down', function (array $case): void {
    $beneficio = BeneficiosTestData::beneficio($case['beneficio']);
    $catalogo = collect(BeneficiosTestData::catalogo()['beneficios']);
    $nit = $catalogo->firstWhere('nombre', $case['beneficio'])['comercio_nit'];
    $cajero = RedencionTestData::cajero($nit);
    $cliente = RedencionTestData::cliente([
        'tipo_documento' => 'CC',
        'documento' => '1099887766',
        'nivel_efectivo' => $case['nivel'],
        'fecha_nacimiento' => CarbonImmutable::today()->subYears(30)->toDateString(),
    ]);
    $entregar = static fn (): Redencion => RedencionTestData::entregar(
        $cajero,
        $cliente,
        $beneficio,
        $case['monto_cuenta'],
        soporte: 'Contrato 1',
    );

    if ($case['expected']['accepted']) {
        $redencion = $entregar();

        expect($redencion->valor_otorgado_cop)->toBe($case['expected']['valor_otorgado_cop'])
            ->and($redencion->monto_cuenta_cop)->toBe($case['monto_cuenta']);

        return;
    }

    expect($entregar)->toThrow(ValidationException::class)
        ->and(fn () => $entregar())->toThrow(function (ValidationException $e) use ($case): void {
            expect($e->errors())->toHaveKey($case['expected']['error_field']);
        })
        ->and(Redencion::query()->count())->toBe(0);
})->with(fn (): array => RedencionTestData::dataset(
    'granted_value',
    static fn (array $case): string => "{$case['beneficio']} {$case['monto_cuenta']}",
));

it('scans and confirms each fixture timeline', function (int $index): void {
    $escenario = $this->fixture['qr'][$index];
    $cliente = ($this->cliente)($escenario['customer']);
    $cajeros = array_map($this->cajero, $escenario['cashiers'] ?? [$escenario['cashier']]);
    $inicio = CarbonImmutable::now();
    $beneficio = isset($escenario['beneficio']) ? BeneficiosTestData::beneficio($escenario['beneficio']) : null;

    foreach ($escenario['previous_redemptions'] ?? [] as $previa) {
        RedencionTestData::redencionPrevia($cajeros[0], $cliente, $beneficio, $previa['date']);
    }

    $emitida = ($escenario['proposito'] ?? 'beneficio') === 'identificar'
        ? app(IssueCredencial::class)($cliente, PropositoCredencial::Identificar)
        : RedencionTestData::emitir($cliente, $beneficio);
    $credencial = $emitida->credencial;

    foreach ($escenario['steps'] as $paso) {
        $this->travelTo($inicio->addSeconds($paso['at_second'] ?? 0));
        $cajero = $cajeros[$paso['cashier_index'] ?? 0];
        $esperado = $paso['expected'] ?? [];
        $contexto = "{$escenario['_description']} · {$paso['action']} at {$paso['at_second']} s";

        if ($paso['action'] === 'scan') {
            $chequeo = app(CheckBeneficioCredencial::class)($cajero, $emitida->token);

            expect($chequeo->resultado->value)->toBe($esperado['result'], $contexto);

            if (isset($esperado['nivel'])) {
                expect($chequeo->cliente?->nivel_efectivo->value)->toBe($esperado['nivel'])
                    ->and($chequeo->estado?->valor)->toBe($esperado['valor']);
            }

            if (array_key_exists('used_at', $esperado)) {
                expect($chequeo->ultimoUso?->created_at->format('Y-m-d H:i:s'))->toBe($esperado['used_at'])
                    ->and($chequeo->ultimoUso?->comercio->razon_social . ' · ' . $chequeo->ultimoUso?->sede?->nombre)
                    ->toBe($esperado['used_in'])
                    ->and($chequeo->vuelve?->toDateString())->toBe($esperado['vuelve']);
            }
        }

        if ($paso['action'] === 'confirm') {
            $entregar = static fn (): Redencion => RedencionTestData::entregar(
                $cajero,
                $cliente,
                $beneficio,
                $paso['monto_cuenta'],
                $credencial->id,
            );

            if ($esperado['accepted']) {
                $redencion = $entregar();

                if (isset($esperado['valor_otorgado_cop'])) {
                    expect($redencion->valor_otorgado_cop)->toBe($esperado['valor_otorgado_cop'])
                        ->and($redencion->sede?->nombre)->toBe($esperado['sede'])
                        ->and($redencion->credencial_qr_id)->toBe($credencial->id)
                        ->and($cliente->fresh()?->saldo_puntos)->toBe($esperado['saldo_puntos'])
                        ->and(MovimientoPuntos::query()->count())->toBe($esperado['new_movements']);
                }
            } else {
                expect(($this->rechazo)($entregar))->toBe($esperado['result'], $contexto);
            }
        }

        if ($paso['action'] === 'cancel') {
            // Cancelar no llama a ninguna Action: no se escribe nada.
            expect(Redencion::query()->count())->toBe($esperado['redemptions']);
        }

        if ($paso['action'] === 'deactivate_benefit') {
            Beneficio::query()->toBase()->where('id', $beneficio?->id)->update(['activo' => false]);
        }

        if (array_key_exists('credential_used', $esperado)) {
            expect(CredencialQr::query()->findOrFail($credencial->id)->usado_at !== null)
                ->toBe($esperado['credential_used'], $contexto);
        }

        if (array_key_exists('redemptions', $esperado)) {
            expect(Redencion::query()->count())->toBe($esperado['redemptions'], $contexto);
        }
    }
})->with(fn (): array => collect(RedencionTestData::fixture()['qr'])
    ->mapWithKeys(static fn (array $escenario, int $i): array => [($i + 1) . '. ' . $escenario['_description'] => [$i]])
    ->all());

it('gives the last global slot to only one customer', function (): void {
    $escenario = $this->fixture['global_quota'][0];
    $beneficio = BeneficiosTestData::beneficio($escenario['beneficio']);
    Beneficio::query()->toBase()->where('id', $beneficio->id)->update(['cupo_global' => $escenario['cupo_global']]);
    $cajero = ($this->cajero)($escenario['cashier']);

    foreach ($escenario['confirmations'] as $confirmacion) {
        $entregar = fn (): Redencion => RedencionTestData::entregar(
            $cajero,
            ($this->cliente)($confirmacion['customer']),
            $beneficio,
            $confirmacion['monto_cuenta'],
        );

        if ($confirmacion['expected']['accepted']) {
            $entregar();
        } else {
            expect(($this->rechazo)($entregar))->toBe($confirmacion['expected']['result']);
        }
    }

    expect(Redencion::query()->count())->toBe($escenario['expected_redemptions']);
});

it('shows the cashier only the benefits of their merchant when searching by document', function (array $caso): void {
    $cajero = ($this->cajero)($caso['cashier']);

    foreach ($this->fixture['customers'] as $datos) {
        ($this->cliente)($datos['key']);
    }

    $cliente = app(FindCliente::class)(
        $cajero,
        TipoDocumento::from($caso['search']['tipo_documento']),
        $caso['search']['documento'],
    );

    if ($caso['expected']['customer'] === null) {
        expect($cliente)->toBeNull();

        return;
    }

    expect($cliente?->id)->toBe(($this->cliente)($caso['expected']['customer'])->id);

    $lista = app(BeneficiosParaCliente::class)->delComercio($cliente, (int) $cajero->comercio_id);

    if (isset($caso['choose'])) {
        $beneficio = BeneficiosTestData::beneficio($caso['choose']);
        $referencia = (string) Illuminate\Support\Str::uuid();

        for ($i = 0; $i < $caso['confirm_times']; $i++) {
            RedencionTestData::entregar($cajero, $cliente, $beneficio, $caso['monto_cuenta'], referencia: $referencia);
        }

        expect(Redencion::query()->count())->toBe($caso['expected']['redemptions'])
            ->and(Redencion::query()->sole()->valor_otorgado_cop)->toBe($caso['expected']['valor_otorgado_cop']);

        return;
    }

    expect(array_map(static fn (BeneficioParaCliente $item): string => $item->beneficio->nombre, $lista))
        ->toBe(array_column($caso['expected']['benefits'], 'beneficio'));

    foreach ($caso['expected']['benefits'] as $i => $esperado) {
        expect($lista[$i]->estado->value)->toBe($esperado['estado']);

        if (isset($esperado['valor'])) {
            expect($lista[$i]->valor)->toBe($esperado['valor']);
        }

        if (isset($esperado['proxima_fecha'])) {
            expect($lista[$i]->proximaFecha?->toDateString())->toBe($esperado['proxima_fecha']);
        }
    }
})->with(fn (): array => RedencionTestData::dataset(
    'document',
    static fn (array $caso): string => $caso['_description'] ?? $caso['cashier'],
));

it('asks for the support only when the benefit requires it', function (array $caso): void {
    $entregar = fn (): Redencion => RedencionTestData::entregar(
        ($this->cajero)($caso['cashier']),
        ($this->cliente)($caso['customer']),
        BeneficiosTestData::beneficio($caso['beneficio']),
        $caso['monto_cuenta'],
        soporte: $caso['soporte'],
    );

    if (! $caso['expected']['accepted']) {
        expect($entregar)->toThrow(ValidationException::class, RedeemBeneficio::SOPORTE)
            ->and(Redencion::query()->count())->toBe(0);

        return;
    }

    $redencion = $entregar();

    expect($redencion->soporte)->toBe($caso['expected']['soporte'])
        ->and($redencion->valor_otorgado_cop)
        ->toBe($caso['expected']['valor_otorgado_cop'] ?? $redencion->valor_otorgado_cop);
})->with(fn (): array => RedencionTestData::dataset(
    'support',
    static fn (array $caso): string => "{$caso['cashier']} «{$caso['soporte']}»",
));

it('refuses a benefit of another merchant and a cashier without a till', function (): void {
    $cliente = ($this->cliente)('andres');
    $kilo = BeneficiosTestData::beneficio('Kilo de carne en El Rancho de Javi');

    expect(fn (): Redencion => RedencionTestData::entregar(($this->cajero)('monarca'), $cliente, $kilo, 100000))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);

    $admin = User::factory()->administradorDelGrupo()->create();

    expect(fn (): Redencion => RedencionTestData::entregar($admin, $cliente, $kilo, 100000))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class)
        ->and(Redencion::query()->count())->toBe(0);
});

it('rejects a code of another customer or benefit at confirmation without burning it', function (): void {
    $andres = ($this->cliente)('andres');
    $lucia = ($this->cliente)('lucia');
    $kilo = BeneficiosTestData::beneficio('Kilo de carne en El Rancho de Javi');
    $credencial = RedencionTestData::emitir($andres, $kilo)->credencial;

    expect(($this->rechazo)(fn (): Redencion => RedencionTestData::entregar(
        ($this->cajero)('rancho'),
        $lucia,
        $kilo,
        100000,
        $credencial->id,
    )))->toBe('invalido')
        ->and($credencial->fresh()?->usado_at)->toBeNull();
});

it('says the next day of a benefit that is not available today', function (): void {
    $chequeo = app(RevisionBeneficio::class)->revisar(
        ($this->cliente)('lucia'),
        BeneficiosTestData::beneficio('Descuento de cumpleaños en Peluquería Monarca')->id,
    );

    expect($chequeo->resultado->value)->toBe('no_disponible_hoy')
        ->and($chequeo->proximaFecha?->toDateString())->toBe('2027-03-15')
        ->and($chequeo->pantalla())->toBe('no_valido');
});

it('counts the redemptions and their granted value on the dashboard, with the scope', function (): void {
    $kilo = BeneficiosTestData::beneficio('Kilo de carne en El Rancho de Javi');
    $cajero = ($this->cajero)('rancho');
    $redencion = RedencionTestData::entregar($cajero, ($this->cliente)('andres'), $kilo, 200000);
    RedencionTestData::entregar(
        ($this->cajero)('monarca'),
        ($this->cliente)('andres'),
        BeneficiosTestData::beneficio('Descuento de cumpleaños en Peluquería Monarca'),
        80000,
    );
    $periodo = Periodo::desde('2026-10');
    $consulta = app(BeneficiosDelPeriodo::class);

    expect($consulta->total($periodo, AlcanceTablero::grupo()))->toBe(['entregados' => 2, 'valor' => 80000])
        ->and($consulta->total($periodo, AlcanceTablero::comercio($kilo->comercio_id)))
        ->toBe(['entregados' => 1, 'valor' => 40000])
        ->and($consulta->porFila($periodo, AlcanceTablero::grupo())[$kilo->comercio_id])
        ->toBe(['entregados' => 1, 'valor' => 40000])
        ->and($consulta->porFila($periodo, AlcanceTablero::comercio($kilo->comercio_id)))
        ->toBe([$redencion->sede_id => ['entregados' => 1, 'valor' => 40000]])
        ->and($consulta->total(Periodo::desde('2026-09'), AlcanceTablero::grupo()))
        ->toBe(['entregados' => 0, 'valor' => 0]);
});

it('writes dates in Colombian Spanish words', function (): void {
    expect(FormatoColombiano::fecha(CarbonImmutable::parse('2026-09-03')))->toBe('3 de septiembre')
        ->and(FormatoColombiano::fecha(CarbonImmutable::parse('2027-01-21')))->toBe('21 de enero');
});
