<?php

declare(strict_types=1);

use App\Actions\Compras\AnnulCompra;
use App\Actions\Compras\CreditPendingCompras;
use App\Actions\Compras\RegisterCompra;
use App\Compras\CompraEnConflicto;
use App\Compras\NuevaCompra;
use App\Compras\RegistroCompra;
use App\Enums\CanalCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use App\Enums\TipoDocumento;
use App\Enums\TipoMovimiento;
use App\Models\CambioNivel;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Models\Transaccion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\ComprasTestData;
use Tests\Support\NivelesTestData;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00:00'));
    $this->admin = User::factory()->administradorDelGrupo()->create();
});

it('leaves one accrual movement per rule when a bonus applies', function (): void {
    $case = ComprasTestData::fixtureCase('a bonus leaves one movement per rule');

    [$data, $this->cliente, $registro] = ComprasTestData::register($case, $this->admin);

    expect($registro->transaccion->estado->value)->toBe($case['expected_estado'])
        ->and(ComprasTestData::movimientosOf($registro->transaccion))
        ->toBe(ComprasTestData::expectedMovimientos($case['expected_movimientos']))
        ->and(MovimientoPuntos::query()->where('transaccion_id', $registro->transaccion->id)->pluck('vigencia_hasta')
            ->map(static fn (mixed $fecha): string => CarbonImmutable::parse((string) $fecha)->toDateString())
            ->unique()
            ->all())
        ->toBe(['2027-10-19'])
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('promotes in the same request that registers the purchase', function (): void {
    $case = ComprasTestData::fixtureCase('synchronous promotion in the same request');

    [$data, $this->cliente, $registro] = ComprasTestData::register($case, $this->admin);

    expect($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo'])
        ->and($this->cliente->fresh()?->nivel_efectivo->value)->toBe($case['expected_nivel_efectivo'])
        ->and($registro->nivelAntes)->toBe(Nivel::Plata)
        ->and($registro->nivelDespues)->toBe(Nivel::Oro)
        ->and($registro->subioDeNivel())->toBeTrue()
        ->and(CambioNivel::query()->sole()->motivo)->toBe(MotivoCambioNivel::AscensoCompra);
});

it('returns the existing purchase on an identical retry, without new points', function (): void {
    $case = ComprasTestData::fixtureCase('an identical retry returns the existing purchase');
    [$data, $this->cliente, $primero] = ComprasTestData::register(
        [...$case, 'cliente' => ['documento' => '1010101010']],
        $this->admin,
    );
    $saldo = $this->cliente->fresh()?->saldo_puntos;

    $retry = app(RegisterCompra::class)($this->admin, $data->compra($case['purchase'], ['documento' => '1010101010']));

    expect($retry->repetida)->toBeTrue()
        ->and($retry->transaccion->id)->toBe($primero->transaccion->id)
        ->and(Transaccion::query()->count())->toBe(1)
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe($saldo);
});

it('rejects a retry with different data as a conflict and changes nothing', function (): void {
    $case = ComprasTestData::fixtureCase('a retry with different data is a conflict');
    [$data, $this->cliente] = ComprasTestData::register(
        [...$case, 'cliente' => ['documento' => '1010101010'], 'purchase' => [...$case['purchase'], 'monto' => 85000]],
        $this->admin,
    );
    $saldo = $this->cliente->fresh()?->saldo_puntos;

    expect(fn (): RegistroCompra => app(RegisterCompra::class)(
        $this->admin,
        $data->compra($case['purchase'], ['documento' => '1010101010']),
    ))->toThrow(CompraEnConflicto::class);

    expect($this->cliente->fresh()?->saldo_puntos)->toBe($saldo)
        ->and(Transaccion::query()->count())->toBe(1);
});

it('stops duplicates in the database, not in code', function (): void {
    $case = ComprasTestData::fixtureCase('concurrent duplicates are stopped by the database');
    $data = ComprasTestData::seed();
    $cliente = $data->cliente($case['cliente']);

    foreach ($case['concurrent_purchases'] as $purchase) {
        app(RegisterCompra::class)($this->admin, $data->compra($purchase, $case['cliente']));
    }

    $duplicate = Transaccion::query()->firstOrFail()->replicate(['id', 'created_at', 'updated_at']);

    expect(Transaccion::query()->count())->toBe($case['expected_transacciones'])
        ->and($cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo'])
        ->and(fn (): bool => $duplicate->save())->toThrow(UniqueConstraintViolationException::class);
});

it('keeps references unique per merchant only', function (): void {
    $case = ComprasTestData::fixtureCase('references are unique per merchant');
    $data = ComprasTestData::seed();
    $cliente = $data->cliente($case['cliente']);

    foreach ($case['purchases'] as $purchase) {
        app(RegisterCompra::class)($this->admin, $data->compra($purchase, $case['cliente']));
    }

    expect(Transaccion::query()->count())->toBe($case['expected_transacciones'])
        ->and($cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('applies the caps with the real history and trims the bonus first', function (string $criterion): void {
    $case = ComprasTestData::fixtureCase($criterion);

    [$data, $this->cliente, $registro] = ComprasTestData::register($case, $this->admin);

    expect(ComprasTestData::movimientosOf($registro->transaccion))
        ->toBe(ComprasTestData::expectedMovimientos($case['expected_movimientos']))
        ->and($registro->puntos->tope)->toBe($case['expected_cap'])
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
})->with([
    'daily cap' => ['daily caps count the customer\'s earlier purchases of the day'],
    'bonus trimmed first' => ['caps trim the bonus points first'],
]);

it('leaves a purchase for an unknown document pending, without points', function (): void {
    $case = ComprasTestData::fixtureCase('a purchase for an unknown document stays pending');

    [$data, $this->cliente, $registro] = ComprasTestData::register($case, $this->admin);

    expect($registro->transaccion->estado->value)->toBe($case['expected_estado'])
        ->and($registro->transaccion->cliente_id)->toBeNull()
        ->and(MovimientoPuntos::query()->count())->toBe(0);
});

it('credits a pending purchase once, with the rules of its purchase date', function (): void {
    $case = ComprasTestData::fixtureCase('pending purchases are credited once with the rules of their purchase date');
    $data = ComprasTestData::seed();
    $transaccion = $data->pendiente($case['pending_transacciones'][0]);
    $v1 = $data->reglas['acumulacion-base'];
    $v1->forceFill(['vigente_hasta' => NivelesTestData::day('hoy-20d')])->save();
    Regla::query()->create([
        'codigo' => 'acumulacion-base',
        'tipo' => $v1->tipo,
        'version' => 2,
        'vigente_desde' => NivelesTestData::day('hoy-20d'),
        'activa' => true,
        'definicion' => [...$v1->definicion, 'tasas_por_categoria' => [
            ['categoria_slug' => 'restaurante', 'puntos_por_unidad' => 2],
            ['categoria_slug' => 'heladeria', 'puntos_por_unidad' => 2],
        ]],
    ]);
    $cliente = $data->cliente($case['cliente']);

    $acreditadas = app(CreditPendingCompras::class)($cliente->id);

    expect($acreditadas)->toHaveCount(1)
        ->and($transaccion->fresh()?->estado->value)->toBe($case['expected_estado'])
        ->and($transaccion->fresh()?->cliente_id)->toBe($cliente->id)
        ->and(ComprasTestData::movimientosOf($transaccion))
        ->toBe(ComprasTestData::expectedMovimientos($case['expected_movimientos']))
        ->and(MovimientoPuntos::query()->where('transaccion_id', $transaccion->id)->sole()
            ->vigencia_hasta?->toDateString())
        ->toBe(NivelesTestData::day('hoy-40d')->addMonths(12)->toDateString())
        ->and($cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('never credits a pending purchase twice', function (): void {
    $case = ComprasTestData::fixtureCase('a pending purchase is never credited twice');
    $data = ComprasTestData::seed();
    $data->pendiente($case['pending_transacciones'][0]);
    $cliente = $data->cliente($case['cliente']);

    app(CreditPendingCompras::class)($cliente->id);
    $second = app(CreditPendingCompras::class)($cliente->id);

    expect($second)->toBe([])
        ->and($cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('credits pending purchases by document type and number', function (): void {
    $case = ComprasTestData::fixtureCase('pending purchases are credited by document type and number');
    $data = ComprasTestData::seed();
    $transaccion = $data->pendiente($case['pending_transacciones'][0]);
    $cliente = $data->cliente($case['cliente']);

    expect(app(CreditPendingCompras::class)($cliente->id))->toBe([])
        ->and($transaccion->fresh()?->estado->value)->toBe($case['expected_estado'])
        ->and($cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('rejects inactive merchants and branches, and branches of another merchant', function (int $index): void {
    $case = ComprasTestData::fixtureCase('merchant and branch must be active and consistent');
    $attempt = $case['attempts'][$index];
    $data = ComprasTestData::seed();
    $data->cliente($case['cliente']);

    expect(fn (): RegistroCompra => app(RegisterCompra::class)($this->admin, $data->compra($attempt, $case['cliente'])))
        ->toThrow(ValidationException::class);

    expect(Transaccion::query()->count())->toBe(0);
})->with(['inactive merchant' => [0], 'inactive branch' => [1], 'branch of another merchant' => [2]]);

it('accepts only positive whole pesos', function (): void {
    $case = ComprasTestData::fixtureCase('amounts are positive integer pesos');
    $data = ComprasTestData::seed();
    $data->cliente($case['cliente']);
    $purchase = ['comercio_id' => 1, 'sede_id' => 11];

    foreach ([0, -5000] as $monto) {
        expect(fn (): RegistroCompra => app(RegisterCompra::class)(
            $this->admin,
            $data->compra([...$purchase, 'monto' => $monto], $case['cliente']),
        ))->toThrow(ValidationException::class, 'mayor que cero');
    }

    expect(fn (): NuevaCompra => $data->compra([...$purchase, 'monto' => 49999.99], $case['cliente']))
        ->toThrow(TypeError::class)
        ->and(Transaccion::query()->count())->toBe(0);
});

it('authorizes by role and branch', function (int $index): void {
    $registrar = ComprasTestData::fixtureCase('authorization by role and branch')['registrars'][$index];
    $data = ComprasTestData::seed();
    $comercio = $registrar['comercio_id'] === null ? null : $data->comercios[$registrar['comercio_id']];
    $sede = $registrar['sede_id'] === null ? null : $data->sedes[$registrar['sede_id']];
    $user = match ($registrar['rol']) {
        'cajero' => User::factory()->cajero($comercio, $sede)->create(),
        'administrador_comercio' => User::factory()->administradorDeComercio($comercio)->create(),
        default => $this->admin,
    };
    $register = fn (): RegistroCompra => app(RegisterCompra::class)(
        $user,
        $data->compra(['comercio_id' => 1, 'sede_id' => 11, 'monto' => 10000], ['documento' => '1010101010']),
    );

    $registrar['expected_result'] === 'accepted'
        ? expect($register())->toBeInstanceOf(RegistroCompra::class)
        : expect($register)->toThrow(AuthorizationException::class);
})->with([
    'cashier of the branch' => [0],
    'cashier of another branch' => [1],
    'cashier of another merchant' => [2],
    'merchant administrator' => [3],
    'administrator of another merchant' => [4],
    'group administrator' => [5],
]);

it('annuls a credited purchase through the reversal and a pending one by marking it', function (): void {
    $case = ComprasTestData::fixtureCase('a bonus leaves one movement per rule');
    [$data, $this->cliente, $registro] = ComprasTestData::register($case, $this->admin);
    $acreditada = $registro->transaccion;
    $pendiente = app(RegisterCompra::class)($this->admin, $data->compra(
        ['comercio_id' => 2, 'sede_id' => 21, 'monto' => 30000, 'documento_cliente' => '9999'],
    ))->transaccion;
    $this->travel(1)->days();

    app(AnnulCompra::class)($this->admin, $acreditada->id, 'Compra anulada en caja');
    app(AnnulCompra::class)($this->admin, $pendiente->id, 'Compra anulada en caja');

    expect($acreditada->fresh()?->estado)->toBe(EstadoTransaccion::Anulada)
        ->and((int) MovimientoPuntos::query()->where('transaccion_id', $acreditada->id)
            ->where('tipo', TipoMovimiento::Reversa->value)->sum('puntos'))->toBe(-170)
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe(0)
        ->and($pendiente->fresh()?->estado)->toBe(EstadoTransaccion::Anulada)
        ->and(MovimientoPuntos::query()->where('transaccion_id', $pendiente->id)->count())->toBe(0)
        ->and(fn (): Transaccion => app(AnnulCompra::class)($this->admin, $pendiente->id, 'Otra vez'))
        ->toThrow(ValidationException::class, 'ya fue anulada');
});

it('registers a purchase that earns no points', function (): void {
    $case = ComprasTestData::fixtureCase('a purchase that earns no points is still registered');

    [$data, $this->cliente, $registro] = ComprasTestData::register($case, $this->admin);

    expect($registro->transaccion->estado->value)->toBe($case['expected_estado'])
        ->and(MovimientoPuntos::query()->where('transaccion_id', $registro->transaccion->id)->count())->toBe(0)
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('registers from the API without a user, and only from the API', function (): void {
    $data = ComprasTestData::seed();
    $compra = fn (CanalCompra $canal): NuevaCompra => $data->compra(
        ['comercio_id' => 2, 'monto' => 10000, 'documento_cliente' => '5555'],
        null,
        $canal,
    );

    expect(app(RegisterCompra::class)(null, $compra(CanalCompra::Api))->transaccion->canal)->toBe(CanalCompra::Api)
        ->and(fn (): RegistroCompra => app(RegisterCompra::class)(null, $compra(CanalCompra::Cajero)))
        ->toThrow(LogicException::class);
});

it('never edits nor deletes a purchase, and only moves its state forward', function (): void {
    $transaccion = Transaccion::factory()->create();

    expect(fn (): bool => $transaccion->update(['monto' => 1]))->toThrow(LogicException::class)
        ->and(fn (): ?bool => $transaccion->fresh()?->delete())->toThrow(LogicException::class);

    $fresh = $transaccion->fresh();
    $fresh?->forceFill(['estado' => EstadoTransaccion::Anulada])->save();

    $back = $transaccion->fresh()?->forceFill(['estado' => EstadoTransaccion::Pendiente]);

    expect(fn (): bool => $back?->save() ?? false)
        ->toThrow(LogicException::class);
});

it('stores the document normalized and finds the customer by type and number', function (): void {
    $data = ComprasTestData::seed();
    $cliente = $data->cliente(['tipo_documento' => 'PPT', 'documento' => 'PPT77']);

    $registro = app(RegisterCompra::class)($this->admin, $data->compra(
        [
            'comercio_id' => 2,
            'sede_id' => 21,
            'monto' => 10000,
            'documento_cliente' => 'ppt 77',
            'tipo_documento_cliente' => 'PPT',
        ],
    ));

    expect($registro->transaccion->documento_cliente)->toBe('PPT77')
        ->and($registro->transaccion->tipo_documento_cliente)->toBe(TipoDocumento::PPT)
        ->and($registro->transaccion->cliente_id)->toBe($cliente->id)
        ->and(Cliente::query()->count())->toBe(1);
});
