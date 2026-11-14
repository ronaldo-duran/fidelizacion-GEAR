<?php

declare(strict_types=1);

use App\Enums\CanalCompra;
use App\Enums\TipoMovimiento;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\ApiTestData;

// API pública (E9-01): autenticación, idempotencia y compras. Los casos salen de
// tests/Fixtures/api-escenarios.json.

beforeEach(function (): void {
    $this->travelTo(ApiTestData::hoy());
    $this->api = ApiTestData::seed();
});

it('keeps the settings equal to the fixture', function (): void {
    $settings = ApiTestData::fixture()['settings'];

    expect(config('api.requests_per_minute'))->toBe($settings['requests_per_minute']['value'])
        ->and(config('api.customer_lookups_per_minute'))->toBe($settings['customer_lookups_per_minute']['value'])
        ->and(config('api.rotation_grace_hours'))->toBe($settings['rotation_grace_hours']['value'])
        ->and(config('api.max_amount'))->toBe($settings['max_amount']['value'])
        ->and(config('puntos.reversal_window_days'))->toBe($settings['annul_window_days']['value']);
});

it('answers each authentication case of the fixture', function (array $caso): void {
    $headers = match ($caso['token'] ?? 'credential') {
        'none' => [],
        'unknown' => ['Authorization' => 'Bearer 99|' . Str::random(40)],
        default => $this->api->headers($caso['credential'] ?? 'pos-rancho'),
    };

    if (in_array($caso['token'] ?? null, ['revoked', 'rotated', 'rotation_new'], true)) {
        [$headers, $horas] = $this->api->tokenSegunCaso($caso);
        $this->travel($horas)->hours();
    }

    $request = $caso['request'] ?? 'GET /api/v1/compras/fac-0001';
    [$metodo, $ruta] = explode(' ', $request, 2);

    if (! isset($caso['request']) && in_array($caso['expected_status'], [200], true)) {
        // Una compra que exista, para que el 200 sea el de la operación.
        $this->postJson('/api/v1/compras', [
            'tipo_documento' => 'CC',
            'documento' => '1012345678',
            'monto' => 1000,
            'referencia_externa' => 'fac-0001',
        ], [...$headers, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    }

    $respuesta = $this->json($metodo, $ruta, [], $headers);

    $respuesta->assertStatus($caso['expected_status']);

    if (isset($caso['expected_codigo'])) {
        $respuesta->assertJsonPath('error.codigo', $caso['expected_codigo']);
    }
})->with(ApiTestData::casos('authentication'));

it('answers each idempotency case of the fixture', function (array $caso): void {
    $requests = $caso['requests'] ?? [[
        'idempotency_key' => $caso['idempotency_key'],
        'body' => $caso['body'],
        'expected_status' => $caso['expected_status'],
        'expected_codigo' => $caso['expected_codigo'] ?? null,
    ]];

    foreach ($requests as $paso) {
        $llave = match ($paso['idempotency_key']) {
            'new' => (string) Str::uuid(),
            default => $paso['idempotency_key'],
        };
        $credencial = $paso['credential'] ?? $caso['credential'];
        $respuesta = $this->postJson('/api/v1/compras', $paso['body'], $this->api->headers($credencial, $llave));

        $respuesta->assertStatus($paso['expected_status']);

        if (($paso['expected_codigo'] ?? null) !== null) {
            $respuesta->assertJsonPath('error.codigo', $paso['expected_codigo']);
        }

        if (array_key_exists('expected_replayed', $paso)) {
            expect($respuesta->headers->get('Idempotent-Replayed'))->toBe($paso['expected_replayed'] ? 'true' : null);
        }

        if (isset($caso['expected_puntos_otorgados']) && $paso['expected_status'] < 300) {
            $respuesta->assertJsonPath('data.puntos_otorgados', $caso['expected_puntos_otorgados']);
        }
    }

    // Solo cuentan las compras de la API: el saldo inicial entró por una compra sembrada.
    expect(Transaccion::query()->where('canal', CanalCompra::Api->value)->count())
        ->toBe($caso['expected_transacciones']);

    if (isset($caso['expected_saldo'])) {
        expect($this->api->clientes['andres']->refresh()->saldo_puntos)->toBe($caso['expected_saldo']);
    }
})->with(ApiTestData::casos('idempotency'));

it('rejects each invalid purchase of the fixture without writing', function (array $caso): void {
    $body = [
        'tipo_documento' => $caso['tipo_documento'] ?? 'CC',
        'documento' => '1012345678',
        'monto' => $caso['monto'],
        'referencia_externa' => $caso['referencia_externa'] ?? 'fac-9001',
    ];

    // Como lo manda un POS: `45000.0` llega con su punto decimal.
    $this->call('POST', '/api/v1/compras', [], [], [], array_merge(
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        $this->transformHeadersToServerVars($this->api->headers('pos-rancho', (string) Str::uuid())),
    ), json_encode($body, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR))
        ->assertStatus($caso['expected_status'])
        ->assertJsonPath('error.codigo', 'datos_invalidos')
        ->assertJsonStructure(['error' => ['campos' => [$caso['expected_campo']]]]);

    expect(Transaccion::query()->where('canal', CanalCompra::Api->value)->count())->toBe(0);
})->with(ApiTestData::casos('validation'));

it('registers each purchase of the fixture with its body', function (array $caso): void {
    $respuesta = $this->postJson(
        '/api/v1/compras',
        $caso['body'],
        $this->api->headers($caso['credential'], (string) Str::uuid()),
    )->assertStatus($caso['expected_status']);

    $esperado = $caso['expected_body'];

    if (array_key_exists('sede_id', $esperado)) {
        $esperado['sede_id'] = $this->api->compras->sedes[$esperado['sede_id']];
    }

    foreach ($esperado as $campo => $valor) {
        $respuesta->assertJsonPath("data.{$campo}", $valor);
    }

    $compra = Transaccion::query()->where('referencia_externa', $caso['body']['referencia_externa'])->sole();

    expect($compra->canal)->toBe(CanalCompra::Api)
        ->and($compra->registrada_por)->toBe($this->api->credenciales[$caso['credential']]->id)
        ->and($compra->idempotency_key)->not->toBeNull();
})->with(ApiTestData::casos('purchases'));

it('reads a purchase of its own merchant and hides the ones of another', function (): void {
    $this->postJson('/api/v1/compras', [
        'tipo_documento' => 'CC',
        'documento' => '1012345678',
        'monto' => 30000,
        'referencia_externa' => 'fac-3001',
    ], $this->api->headers('pos-rancho', (string) Str::uuid()))->assertCreated();

    $this->getJson('/api/v1/compras/FAC-3001', $this->api->headers('pos-rancho'))
        ->assertOk()
        ->assertJsonPath('data.puntos_otorgados', 30)
        ->assertJsonMissingPath('data.saldo_puntos');

    $this->getJson('/api/v1/compras/fac-3001', $this->api->headers('pos-heladeria'))
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'compra_no_encontrada');
});

it('annuls each purchase of the fixture', function (array $caso): void {
    $setup = $caso['setup'];
    $referencia = $setup['referencia_externa'];

    if (($setup['canal'] ?? 'api') === 'cajero') {
        $cajero = App\Models\User::factory()
            ->cajero($this->api->comercioId($setup['comercio_id']), $this->api->compras->sedes[$setup['sede_id']])
            ->create();
        app(App\Actions\Compras\RegisterCompra::class)($cajero, $this->api->compras->compra([
            'comercio_id' => $setup['comercio_id'],
            'sede_id' => $setup['sede_id'],
            'documento_cliente' => '1012345678',
            'monto' => $setup['monto'],
            'referencia_externa' => $referencia,
        ]));
    } else {
        $this->travelTo(ApiTestData::hoy()->subDays($setup['days_ago'] ?? 0));
        $this->postJson('/api/v1/compras', [
            'tipo_documento' => 'CC',
            'documento' => $setup['documento_sin_cliente'] ?? '1012345678',
            'monto' => $setup['monto'],
            'referencia_externa' => $referencia,
        ], $this->api->headers('pos-rancho', (string) Str::uuid()))->assertCreated();
        $this->travelTo(ApiTestData::hoy());
    }

    $url = "/api/v1/compras/{$referencia}/anulacion";

    if ($setup['already_annulled'] ?? false) {
        $this->postJson($url, ['motivo' => 'Primera'], $this->api->headers('pos-rancho', (string) Str::uuid()))
            ->assertOk();
    }

    $respuesta = $this->postJson($url, $caso['body'], $this->api->headers($caso['credential'], (string) Str::uuid()))
        ->assertStatus($caso['expected_status']);
    $compra = Transaccion::query()->where('referencia_externa', $referencia)->sole();
    $reversas = MovimientoPuntos::query()->where('transaccion_id', $compra->id)
        ->where('tipo', TipoMovimiento::Reversa->value);

    match (true) {
        isset($caso['expected_codigo']) => $respuesta->assertJsonPath('error.codigo', $caso['expected_codigo']),
        isset($caso['expected_campo']) => $respuesta->assertJsonStructure([
            'error' => ['campos' => [$caso['expected_campo']]],
        ]),
        default => $respuesta->assertJsonPath('data.estado', 'anulada'),
    };

    if (isset($caso['expected_estado'])) {
        expect($compra->estado->value)->toBe($caso['expected_estado']);
    }

    if (isset($caso['expected_reversa_puntos'])) {
        expect((int) $reversas->sum('puntos'))->toBe($caso['expected_reversa_puntos']);
    }

    if (isset($caso['expected_reversas'])) {
        expect($reversas->count())->toBe($caso['expected_reversas'])
            ->and($respuesta->headers->get('Idempotent-Replayed'))->toBe('true');
    }

    if (isset($caso['expected_movimientos'])) {
        expect(MovimientoPuntos::query()->where('transaccion_id', $compra->id)->count())
            ->toBe($caso['expected_movimientos']);
    }
})->with(ApiTestData::casos('annulment'));

it('answers every error of /api with the same JSON shape', function (array $caso): void {
    Route::middleware('api')->get('api/v1/forzar-error', static function (): never {
        throw new RuntimeException('SQLSTATE[42P01]: Undefined table: select * from secreta');
    });

    [$metodo, $ruta] = $caso['request'] === 'forced_exception'
        ? ['GET', '/api/v1/forzar-error']
        : explode(' ', $caso['request'], 2);

    $respuesta = $this->json($metodo, $ruta, [], $this->api->headers('pos-rancho'))
        ->assertStatus($caso['expected_status'])
        ->assertJsonPath('error.codigo', $caso['expected_codigo'])
        ->assertJsonStructure(['error' => ['codigo', 'mensaje', 'campos']]);

    foreach ($caso['forbidden_text'] ?? [] as $texto) {
        expect($respuesta->getContent())->not->toContain($texto);
    }
})->with(ApiTestData::casos('errors'));

it('rejects a POST without a valid Idempotency-Key on every write', function (string $ruta): void {
    $this->postJson($ruta, [], $this->api->headers('pos-rancho'))
        ->assertStatus(400)
        ->assertJsonPath('error.codigo', 'idempotency_key_requerida');
})->with(['/api/v1/compras', '/api/v1/compras/fac-1/anulacion']);
