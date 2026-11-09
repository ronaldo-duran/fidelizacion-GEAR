<?php

declare(strict_types=1);

use App\Enums\CanalCompra;
use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use Illuminate\Support\Str;
use Tests\Support\ApiTestData;
use Tests\Support\BloqueoTestData;
use Tests\Support\SinTransaccionDePrueba;

// Concurrencia real de E9-01 (criterio del issue #25): diez procesos PHP registran la misma
// compra a la vez contra la base de prueba. La protección es la
// restricción única de cada tabla (regla dura 3.7). Los datos se guardan de verdad para que los
// procesos los vean.
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
