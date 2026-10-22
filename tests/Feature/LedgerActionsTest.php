<?php

declare(strict_types=1);

use App\Actions\Puntos\RecalculateSaldo;
use App\Actions\Puntos\RecordAcumulacion;
use App\Actions\Puntos\RecordAjusteManual;
use App\Actions\Puntos\RecordCaducidad;
use App\Actions\Puntos\RecordPromocion;
use App\Actions\Puntos\ReverseTransaccion;
use App\Enums\EstadoTransaccion;
use App\Enums\TipoMovimiento;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\LedgerTestData;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 10:00:00'));
    $this->comercio = Comercio::factory()->create();
    $this->regla = LedgerTestData::acumulacionRegla();
    $this->cliente = LedgerTestData::cliente();
    $this->admin = User::factory()->administradorDelGrupo()->create();

    $this->transacciones = [];
    $this->tx = fn (int $key): int => $this->transacciones[$key] ??= Transaccion::factory()->create([
        'comercio_id' => $this->comercio->id,
        'cliente_id' => $this->cliente->id,
        'estado' => EstadoTransaccion::Acreditada,
    ])->id;

    $this->accrue = function (int $transaccionId, int $puntos, ?CarbonImmutable $at = null): MovimientoPuntos {
        $now = CarbonImmutable::now();
        $this->travelTo($at ?? $now);
        $movimiento = app(RecordAcumulacion::class)(
            $this->cliente->id,
            $this->comercio->id,
            ($this->tx)($transaccionId),
            $puntos,
            $this->regla,
            CarbonImmutable::now()->addMonths(12),
        );
        $this->travelTo($now);

        return $movimiento;
    };
});

it('credits exactly the accrued points in the same transaction', function (): void {
    $case = LedgerTestData::fixtureCase('the balance grew by exactly N in the same transaction');

    $movimiento = ($this->accrue)(1001, $case['movimientos'][0]['puntos']);

    expect($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo'])
        ->and($movimiento->tipo)->toBe(TipoMovimiento::Acumulacion)
        ->and($movimiento->regla_id)->toBe($this->regla->id)
        ->and($movimiento->regla_version)->toBe(1)
        ->and($movimiento->vigencia_hasta?->toDateString())->toBe('2027-10-17');
});

it('reverses a purchase within the window and leaves the original untouched', function (): void {
    $original = ($this->accrue)(1001, 85, CarbonImmutable::now()->subDays(10));

    $reversals = app(ReverseTransaccion::class)($this->admin, ($this->tx)(1001), 'Compra anulada en caja');

    expect($reversals)->toHaveCount(1)
        ->and($reversals[0]->puntos)->toBe(-85)
        ->and($reversals[0]->transaccion_id)->toBe(($this->tx)(1001))
        ->and($reversals[0]->regla_id)->toBe($this->regla->id)
        ->and($reversals[0]->usuario_id)->toBe($this->admin->id)
        ->and($original->fresh()?->puntos)->toBe(85)
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe(0);
});

it('rejects a reversal older than the window and inserts nothing', function (): void {
    ($this->accrue)(1002, 85, CarbonImmutable::now()->subDays(31));

    expect(fn (): array => app(ReverseTransaccion::class)($this->admin, ($this->tx)(1002), 'Anulada'))
        ->toThrow(ValidationException::class);

    expect(MovimientoPuntos::query()->count())->toBe(1);
});

it('accepts a reversal on the last day of the window at any hour', function (): void {
    expect(config('puntos.reversal_window_days'))->toBe(LedgerTestData::fixture()['reversal_window_days']);

    ($this->accrue)(1003, 85, CarbonImmutable::parse('2026-09-17 00:05:00'));
    $this->travelTo(CarbonImmutable::parse('2026-10-17 23:55:00'));

    expect(app(ReverseTransaccion::class)($this->admin, ($this->tx)(1003), 'Anulada'))->toHaveCount(1);
});

it('rejects a second reversal of the same purchase', function (): void {
    ($this->accrue)(1004, 85, CarbonImmutable::now()->subDays(5));
    app(ReverseTransaccion::class)($this->admin, ($this->tx)(1004), 'Anulada');

    expect(fn (): array => app(ReverseTransaccion::class)($this->admin, ($this->tx)(1004), 'Otra vez'))
        ->toThrow(ValidationException::class);

    expect(MovimientoPuntos::query()->where('tipo', 'reversa')->count())->toBe(1);
});

it('caps the reversal at what is left of the purchase lot', function (): void {
    $case = LedgerTestData::fixtureCase('reversal capped at the purchase lot remainder');
    ($this->accrue)(1005, 85, CarbonImmutable::now()->subDays(5));
    app(RecordAjusteManual::class)($this->admin, $this->cliente->id, -35, 'Corrección');

    $reversals = app(ReverseTransaccion::class)($this->admin, ($this->tx)(1005), 'Anulada');

    expect($reversals[0]->puntos)->toBe($case['expected_movimiento']['puntos'])
        ->and($reversals[0]->motivo)->toContain('topada')
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('never takes points of another purchase when reversing', function (): void {
    $case = LedgerTestData::fixtureCase('a reversal never takes points of another purchase');
    ($this->accrue)(2001, 85, CarbonImmutable::now()->subDays(10));
    app(RecordAjusteManual::class)($this->admin, $this->cliente->id, -85, 'Corrección');
    ($this->accrue)(2002, 50, CarbonImmutable::now()->subDays(2));

    $reversals = app(ReverseTransaccion::class)($this->admin, ($this->tx)(2001), 'Anulada');

    expect($reversals)->toHaveCount($case['expected_reversa_movimientos'])
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('records a manual adjustment with its reason and user and rejects one without a reason', function (): void {
    ($this->accrue)(3001, 500);

    $ajuste = app(RecordAjusteManual::class)($this->admin, $this->cliente->id, -40, 'Corrección de doble registro');

    expect($ajuste->motivo)->toBe('Corrección de doble registro')
        ->and($ajuste->usuario_id)->toBe($this->admin->id)
        ->and($ajuste->regla_id)->toBeNull()
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe(460)
        ->and(fn (): MovimientoPuntos => app(RecordAjusteManual::class)($this->admin, $this->cliente->id, -40, '  '))
        ->toThrow(ValidationException::class);
});

it('gives a positive adjustment the validity of the accrual rule in force', function (): void {
    $ajuste = app(RecordAjusteManual::class)($this->admin, $this->cliente->id, 30, 'Cortesía');

    expect($ajuste->vigencia_hasta?->toDateString())->toBe('2027-10-17');
});

it('rejects a movement that would leave the balance below zero', function (): void {
    ($this->accrue)(3002, 30);

    $overdraw = fn (): MovimientoPuntos => app(RecordAjusteManual::class)(
        $this->admin,
        $this->cliente->id,
        -40,
        'Corrección',
    );

    expect($overdraw)->toThrow(ValidationException::class);

    expect($this->cliente->fresh()?->saldo_puntos)->toBe(30);
});

it('expires points with their own movement', function (): void {
    ($this->accrue)(3003, 300);

    $caducidad = app(RecordCaducidad::class)($this->cliente->id, 120, $this->regla);

    expect($caducidad->puntos)->toBe(-120)
        ->and($caducidad->tipo)->toBe(TipoMovimiento::Caducidad)
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe(180);
});

it('records a promotion with its rule snapshot', function (): void {
    $vigencia = CarbonImmutable::now()->addYear();
    $promocion = app(RecordPromocion::class)($this->cliente->id, null, 400, $this->regla, $vigencia);

    expect($promocion->tipo)->toBe(TipoMovimiento::Promocion)
        ->and($promocion->comercio_id)->toBeNull()
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe(400);
});

it('recalculates the balance from scratch after every movement type', function (): void {
    $case = LedgerTestData::fixtureCase('recalculated balance == materialized balance');
    ($this->accrue)(4001, 85, CarbonImmutable::now()->subDays(3));
    ($this->accrue)(4002, 200, CarbonImmutable::now()->subDays(2));
    app(ReverseTransaccion::class)($this->admin, ($this->tx)(4001), 'Anulada');
    app(RecordAjusteManual::class)($this->admin, $this->cliente->id, 30, 'Cortesía');
    app(RecordCaducidad::class)($this->cliente->id, 50, $this->regla);

    $check = app(RecalculateSaldo::class)($this->cliente->id);

    expect($check->matches())->toBeTrue()
        ->and($check->calculated)->toBe($case['expected_saldo']);
});

it('never updates or deletes a movement', function (): void {
    $movimiento = ($this->accrue)(5001, 85);

    expect(fn (): bool => $movimiento->update(['puntos' => 1]))->toThrow(LogicException::class)
        ->and(fn (): ?bool => $movimiento->delete())->toThrow(LogicException::class);
});

it('rolls back the movement and the balance when the operation fails halfway', function (): void {
    ($this->accrue)(6001, 100);
    MovimientoPuntos::created(static function (): void {
        throw new RuntimeException('Simulated failure after inserting the movement.');
    });

    expect(fn (): MovimientoPuntos => ($this->accrue)(6002, 50))->toThrow(RuntimeException::class);

    expect(MovimientoPuntos::query()->count())->toBe(1)
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe(100);
});

it('locks the customer row before writing a movement', function (): void {
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = mb_strtolower($query->sql);
    });

    ($this->accrue)(7001, 10);

    $locks = array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'clientes')
        && str_contains($sql, 'for update'));

    expect($locks)->not->toBeEmpty();
});

it('authorizes reversals and manual adjustments by role and merchant', function (
    string $action,
    string $rol,
    ?string $comercio,
    string $expected,
): void {
    $otroComercio = Comercio::factory()->create();
    $comercioId = $comercio === 'own' ? $this->comercio->id : $otroComercio->id;
    $user = match ($rol) {
        'administrador_grupo' => User::factory()->administradorDelGrupo()->create(),
        'administrador_comercio' => User::factory()->administradorDeComercio($comercioId)->create(),
        default => User::factory()->cajero($comercioId)->create(),
    };
    ($this->accrue)(8001, 85, CarbonImmutable::now()->subDay());

    $run = $action === 'reversa'
        ? fn (): array => app(ReverseTransaccion::class)($user, ($this->tx)(8001), 'Anulada')
        : fn (): MovimientoPuntos => app(RecordAjusteManual::class)($user, $this->cliente->id, -10, 'Corrección');

    if ($expected === 'forbidden') {
        expect($run)->toThrow(AuthorizationException::class);

        return;
    }

    expect($run())->not->toBeNull();
})->with(array_map(
    static fn (array $row): array => [$row['action'], $row['rol'], $row['comercio'] ?? null, $row['expected_result']],
    LedgerTestData::fixtureCase('authorization by role and merchant')['authorization'],
));

it('rejects movements whose sign or fields do not match their type', function (array $movimiento): void {
    $attributes = array_diff_key($movimiento, ['_reason' => true]);
    $cliente = Cliente::query()->firstOrFail();

    expect(fn (): MovimientoPuntos => MovimientoPuntos::query()->create([...$attributes, 'cliente_id' => $cliente->id]))
        ->toThrow(LogicException::class);
})->with(array_map(
    static fn (array $row): array => [$row],
    LedgerTestData::fixtureCase('sign and fields must match the type')['invalid_movimientos'],
));
