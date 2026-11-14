<?php

declare(strict_types=1);

use App\Actions\Api\AnnulCompraApi;
use App\Actions\Compras\RegisterCompra;
use App\Compras\NuevaCompra;
use App\Enums\CanalCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\TipoDocumento;
use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;
use App\Models\Redencion;
use App\Models\Transaccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ApiTestData;
use Tests\Support\BloqueoTestData;
use Tests\Support\SinTransaccionDePrueba;

// Concurrencia real de E9-01 (criterio del issue #25): diez procesos PHP registran o anulan la
// misma compra, o entregan con la misma llave, a la vez contra la base de prueba. La protección
// es la restricción única de cada tabla y el bloqueo del cliente en la reversa (reglas duras 3.4
// y 3.7). Los datos se guardan de verdad para que los procesos los vean.
uses(SinTransaccionDePrueba::class);

afterEach(function (): void {
    // vaciar() suelta la segunda conexión: aquí no se usó, pero debe existir.
    BloqueoTestData::segundaConexion();
    BloqueoTestData::vaciar();
});

it('gives one purchase and one credit to ten simultaneous retries', function (): void {
    $caso = ApiTestData::fixture()['concurrency'][0];
    $api = ApiTestData::seed();
    $credencial = $api->credenciales[$caso['credential']];

    $estados = ApiTestData::enParalelo('compra', $credencial->id, [
        ...$caso['body'],
        'idempotency_key' => (string) Str::uuid(),
    ], $caso['parallel_requests']);

    $compra = Transaccion::query()->where('canal', CanalCompra::Api->value)->sole();

    $esperados = array_map(intval(...), $caso['expected_status_counts']);
    ksort($esperados);

    expect($estados)->toBe($esperados)
        ->and(Transaccion::query()->where('canal', CanalCompra::Api->value)->count())
        ->toBe($caso['expected_transacciones'])
        ->and(MovimientoPuntos::query()->where('transaccion_id', $compra->id)
            ->where('tipo', TipoMovimiento::Acumulacion->value)->count())
        ->toBe($caso['expected_movimientos'])
        ->and($api->clientes['andres']->refresh()->saldo_puntos)->toBe($caso['expected_saldo']);
});

it('gives one reversal to ten simultaneous annulments of the same purchase', function (): void {
    $caso = ApiTestData::fixture()['concurrency'][2];
    $api = ApiTestData::seed();
    $credencial = $api->credenciales[$caso['credential']];
    $cliente = $api->clientes[$caso['setup']['customer']];
    app(RegisterCompra::class)($credencial, new NuevaCompra(
        (int) $credencial->comercio_id,
        $credencial->sede_id,
        TipoDocumento::from($cliente->tipo_documento->value),
        $cliente->documento,
        $caso['setup']['monto'],
        $caso['setup']['referencia_externa'],
        CanalCompra::Api,
        (string) Str::uuid(),
    ));

    $estados = ApiTestData::enParalelo('anulacion', $credencial->id, [
        'referencia_externa' => $caso['setup']['referencia_externa'],
        ...$caso['body'],
    ], $caso['parallel_requests']);

    $compra = Transaccion::query()->where('referencia_externa', $caso['setup']['referencia_externa'])->sole();
    $reversas = MovimientoPuntos::query()->where('transaccion_id', $compra->id)
        ->where('tipo', TipoMovimiento::Reversa->value);
    $esperados = array_map(intval(...), $caso['expected_status_counts']);
    ksort($esperados);

    expect($estados)->toBe($esperados)
        ->and($compra->estado)->toBe(EstadoTransaccion::from($caso['expected_estado']))
        ->and($reversas->count())->toBe($caso['expected_reversas'])
        ->and((int) $reversas->sum('puntos'))->toBe($caso['expected_reversa_puntos'])
        ->and($cliente->refresh()->saldo_puntos)->toBe($caso['expected_saldo']);
});

it('gives one redemption to ten simultaneous deliveries with the same key', function (): void {
    $caso = ApiTestData::fixture()['concurrency'][1];
    $api = ApiTestData::seed();
    $beneficio = $api->beneficio(1);

    $estados = ApiTestData::enParalelo('redencion', $api->credenciales[$caso['credential']]->id, [
        'tipo_documento' => 'CC',
        'documento' => '1012345678',
        'beneficio_id' => $beneficio->id,
        'idempotency_key' => $caso['idempotency_key'],
    ], $caso['parallel_requests']);

    $esperados = array_map(intval(...), $caso['expected_status_counts']);
    ksort($esperados);

    expect($estados)->toBe($esperados)
        ->and(Redencion::query()->count())->toBe($caso['expected_redenciones']);
});

it('spends a code once when ten deliveries with the same code and key arrive together', function (): void {
    $caso = ApiTestData::fixture()['concurrency'][3];
    $api = ApiTestData::seed();
    $codigo = ApiTestData::codigo($api->clientes['andres'], $api->beneficio(1));

    $estados = ApiTestData::enParalelo('redencion', $api->credenciales[$caso['credential']]->id, [
        'codigo' => $codigo,
        'idempotency_key' => $caso['idempotency_key'],
    ], $caso['parallel_requests']);

    $esperados = array_map(intval(...), $caso['expected_status_counts']);
    ksort($esperados);

    expect($estados)->toBe($esperados)
        ->and(Redencion::query()->count())->toBe($caso['expected_redenciones'])
        ->and(Redencion::query()->sole()->credencial_qr_id)->not->toBeNull();
});

it('waits for the purchase lock before annulling it', function (): void {
    $caso = ApiTestData::fixture()['concurrency'][2];
    $api = ApiTestData::seed();
    $credencial = $api->credenciales[$caso['credential']];
    $cliente = $api->clientes[$caso['setup']['customer']];
    $compra = app(RegisterCompra::class)($credencial, new NuevaCompra(
        (int) $credencial->comercio_id,
        $credencial->sede_id,
        TipoDocumento::from($cliente->tipo_documento->value),
        $cliente->documento,
        $caso['setup']['monto'],
        $caso['setup']['referencia_externa'],
        CanalCompra::Api,
        (string) Str::uuid(),
    ))->transaccion;

    // Otra anulación tiene la compra bloqueada: esta espera ahí, antes de leer el libro.
    $segunda = BloqueoTestData::segundaConexion();
    $segunda->beginTransaction();
    $segunda->table('transacciones')->where('id', $compra->id)->lockForUpdate()->first();
    BloqueoTestData::limitarEspera(DB::connection(), 1);

    $bloqueada = null;

    try {
        app(AnnulCompraApi::class)($credencial, $caso['setup']['referencia_externa'], $caso['body']['motivo']);
    } catch (QueryException $error) {
        $bloqueada = $error;
    }

    expect($bloqueada)->toBeInstanceOf(QueryException::class)
        ->and($bloqueada?->getSql())->toContain('transacciones')
        ->and(MovimientoPuntos::query()->where('tipo', TipoMovimiento::Reversa->value)->count())->toBe(0);

    $segunda->rollBack();
});
