<?php

declare(strict_types=1);

use App\Actions\Beneficios\CheckBeneficioCredencial;
use App\Actions\Beneficios\RedeemBeneficio;
use App\Actions\Credenciales\ValidateCredencial;
use App\Beneficios\ChequeoBeneficio;
use App\Beneficios\EntregaBeneficio;
use App\Beneficios\RevisionBeneficio;
use App\Enums\EstadoTransaccion;
use App\Models\Redencion;
use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\ApiTestData;

// API pública (E9-01): aislamiento entre comercios, consulta de clientes, beneficios y
// redenciones. Los casos salen de tests/Fixtures/api-escenarios.json.

beforeEach(function (): void {
    $this->travelTo(ApiTestData::hoy());
    $this->api = ApiTestData::seed();
    $this->andres = $this->api->clientes['andres'];
});

it('answers a customer lookup with the first name and the level only', function (): void {
    $caso = ApiTestData::fixture()['customer_lookup'][0];

    $url = '/api/v1/clientes?' . http_build_query($caso['query']);
    $respuesta = $this->getJson($url, $this->api->headers('pos-rancho'))->assertOk();

    foreach ($caso['expected_body'] as $campo => $valor) {
        $respuesta->assertJsonPath("data.{$campo}", $valor);
    }

    foreach ($caso['forbidden_fields'] as $campo) {
        $respuesta->assertJsonMissingPath("data.{$campo}");
    }

    expect($respuesta->getContent())->not->toContain('3001234567')->not->toContain('andres@example.com');
});

it('answers 404 to a document without a customer', function (array $caso): void {
    $this->getJson('/api/v1/clientes?' . http_build_query($caso['query']), $this->api->headers('pos-rancho'))
        ->assertStatus($caso['expected_status'])
        ->assertJsonPath('error.codigo', $caso['expected_codigo']);
})->with(fn (): array => array_slice(ApiTestData::casos('customer_lookup'), 1, 2));

it('answers 429 with Retry-After past the lookup limit', function (): void {
    $caso = ApiTestData::fixture()['customer_lookup'][3];
    $url = '/api/v1/clientes?tipo_documento=CC&documento=1012345678';

    for ($i = 1; $i < $caso['lookups_in_the_minute']; $i++) {
        $this->getJson($url, $this->api->headers('pos-rancho'))->assertOk();
    }

    $respuesta = $this->getJson($url, $this->api->headers('pos-rancho'))
        ->assertStatus($caso['expected_last_status'])
        ->assertJsonPath('error.codigo', $caso['expected_codigo']);

    expect($respuesta->headers->has('Retry-After'))->toBe($caso['expected_retry_after_header']);

    // Otra credencial tiene su propio tope.
    $this->getJson($url, $this->api->headers('pos-heladeria'))->assertOk();
});

it('lists only the benefits of the credential merchant with their state', function (): void {
    $propio = $this->api->beneficio(2);
    $this->api->beneficio(1);

    $respuesta = $this->getJson(
        '/api/v1/clientes/beneficios?tipo_documento=CC&documento=1012345678',
        $this->api->headers('pos-heladeria'),
    )->assertOk();

    expect($respuesta->json('data.*.beneficio_id'))->toBe([$propio->id])
        ->and($respuesta->json('data.0.estado'))->toBe('disponible')
        ->and($respuesta->json('data.0.valor'))->toBe(5000);
});

it('hides a purchase of another merchant and does not annul it', function (): void {
    $this->postJson('/api/v1/compras', [
        'tipo_documento' => 'CC',
        'documento' => '1012345678',
        'monto' => 30000,
        'referencia_externa' => 'fac-3002',
    ], $this->api->headers('pos-rancho', (string) Str::uuid()))->assertCreated();

    $headers = $this->api->headers('pos-heladeria', (string) Str::uuid());

    $this->postJson('/api/v1/compras/fac-3002/anulacion', ['motivo' => 'Devolución'], $headers)
        ->assertNotFound()
        ->assertJsonPath('error.codigo', 'compra_no_encontrada');

    expect(Transaccion::query()->where('referencia_externa', 'fac-3002')->sole()->estado)
        ->toBe(EstadoTransaccion::Acreditada);
});

it('answers not valid to a benefit code of another merchant', function (): void {
    $codigo = ApiTestData::codigo($this->andres, $this->api->beneficio(1));

    $this->getJson("/api/v1/codigos/{$codigo}", $this->api->headers('pos-heladeria'))
        ->assertOk()
        ->assertJsonPath('data.resultado', 'no_valido')
        ->assertJsonPath('data.cliente', null);
});

it('checks a code, delivers it once with the same key and then sees it used', function (): void {
    $caso = ApiTestData::fixture()['redemption'][0];
    $beneficio = $this->api->beneficio(1);
    $codigo = ApiTestData::codigo($this->andres, $beneficio);
    $saldo = $this->andres->saldo_puntos;
    $llave = $caso['steps'][1]['idempotency_key'];

    $this->getJson("/api/v1/codigos/{$codigo}", $this->api->headers('pos-rancho'))
        ->assertOk()
        ->assertJsonPath('data.resultado', 'valido')
        ->assertJsonPath('data.cliente.primer_nombre', 'Andrés')
        ->assertJsonPath('data.beneficio.valor', 5000);

    $creada = $this->postJson('/api/v1/redenciones', ['codigo' => $codigo], $this->api->headers('pos-rancho', $llave))
        ->assertCreated()
        ->assertJsonPath('data.valor_otorgado_cop', 5000)
        ->assertJsonPath('data.referencia', $llave)
        ->assertJsonPath('data.sede_id', $this->api->compras->sedes[11]);

    foreach ($caso['steps'][1]['expected_fields'] as $campo) {
        expect($creada->json("data.{$campo}"))->not->toBeNull();
    }

    $repetida = $this->postJson('/api/v1/redenciones', ['codigo' => $codigo], $this->api->headers('pos-rancho', $llave))
        ->assertOk();

    expect($repetida->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and($repetida->json('data'))->toBe($creada->json('data'));

    $this->getJson("/api/v1/codigos/{$codigo}", $this->api->headers('pos-rancho'))
        ->assertOk()
        ->assertJsonPath('data.resultado', 'ya_usado');

    $andres = $this->andres->refresh();

    expect(Redencion::query()->count())->toBe($caso['expected_redenciones'])
        ->and($andres->saldo_puntos)->toBe($saldo)
        ->and($andres->nivel_efectivo->value)->toBe('bronce')
        ->and(Redencion::query()->sole()->validado_por)->toBe($this->api->credenciales['pos-rancho']->id);
});

it('delivers by document and answers a conflict for the same key with another benefit', function (): void {
    $primero = $this->api->beneficio(1);
    $otro = $this->api->beneficio(1);
    $llave = (string) Str::uuid();
    $body = ['tipo_documento' => 'CC', 'documento' => '1012345678', 'beneficio_id' => $primero->id];

    $this->postJson('/api/v1/redenciones', $body, $this->api->headers('pos-rancho', $llave))->assertCreated();

    $headers = $this->api->headers('pos-rancho', $llave);

    $this->postJson('/api/v1/redenciones', [...$body, 'beneficio_id' => $otro->id], $headers)
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'redencion_en_conflicto');

    $otroCliente = [...$body, 'documento' => 'B7K2M9Q4', 'tipo_documento' => 'PPT'];

    $this->postJson('/api/v1/redenciones', $otroCliente, $this->api->headers('pos-rancho', $llave))
        ->assertStatus(409);

    expect(Redencion::query()->count())->toBe(1);
});

it('answers a conflict to a delivery key already used by another merchant', function (): void {
    $llave = ApiTestData::fixture()['isolation'][4]['idempotency_key'];
    $body = ['tipo_documento' => 'CC', 'documento' => '1012345678'];

    $propio = [...$body, 'beneficio_id' => $this->api->beneficio(1)->id];
    $ajeno = [...$body, 'beneficio_id' => $this->api->beneficio(2)->id];

    $this->postJson('/api/v1/redenciones', $propio, $this->api->headers('pos-rancho', $llave))
        ->assertCreated();

    $this->postJson('/api/v1/redenciones', $ajeno, $this->api->headers('pos-heladeria', $llave))
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'redencion_en_conflicto')
        ->assertJsonMissingPath('error.campos.comercio');

    expect(Redencion::query()->count())->toBe(1);
});

it('stores the granted value of a percentage from the bill amount', function (): void {
    $beneficio = $this->api->beneficio(1, [
        'tipo' => 'porcentaje',
        'valores_por_nivel' => ['bronce' => 15, 'plata' => 20, 'oro' => 30, 'diamante' => 40],
    ]);
    $body = ['tipo_documento' => 'CC', 'documento' => '1012345678', 'beneficio_id' => $beneficio->id];

    $this->postJson('/api/v1/redenciones', $body, $this->api->headers('pos-rancho', (string) Str::uuid()))
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['campos' => ['monto_cuenta_cop']]]);

    // 15 % de $33.333 son $4.999,95: hacia abajo, $4.999 (regla dura 3.9).
    $conMonto = [...$body, 'monto_cuenta_cop' => 33333];

    $this->postJson('/api/v1/redenciones', $conMonto, $this->api->headers('pos-rancho', (string) Str::uuid()))
        ->assertCreated()
        ->assertJsonPath('data.valor_otorgado_cop', 4999)
        ->assertJsonPath('data.monto_cuenta_cop', 33333);
});

it('asks for the support of a benefit that requires it', function (): void {
    $beneficio = $this->api->beneficio(1, ['requiere_soporte' => true]);
    $body = ['tipo_documento' => 'CC', 'documento' => '1012345678', 'beneficio_id' => $beneficio->id];

    $this->postJson('/api/v1/redenciones', $body, $this->api->headers('pos-rancho', (string) Str::uuid()))
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['campos' => ['soporte']]]);

    expect(Redencion::query()->count())->toBe(0);
});

it('rejects a delivery without a valid Idempotency-Key', function (string $ruta): void {
    $this->postJson($ruta, [], $this->api->headers('pos-rancho'))
        ->assertStatus(400)
        ->assertJsonPath('error.codigo', 'idempotency_key_requerida');
})->with(['/api/v1/redenciones']);

it('answers 429 with Retry-After when a delivery by document passes the lookup limit', function (): void {
    $beneficio = $this->api->beneficio(1);
    $body = ['tipo_documento' => 'CC', 'documento' => '1012345678', 'beneficio_id' => $beneficio->id];
    $tope = config('api.customer_lookups_per_minute');

    // Las consultas por documento comparten el tope de la credencial.
    for ($i = 0; $i < $tope; $i++) {
        $this->getJson('/api/v1/clientes?tipo_documento=CC&documento=1012345678', $this->api->headers('pos-rancho'));
    }

    $respuesta = $this->postJson('/api/v1/redenciones', $body, $this->api->headers('pos-rancho', (string) Str::uuid()))
        ->assertStatus(429)
        ->assertJsonPath('error.codigo', 'demasiadas_peticiones');

    expect($respuesta->headers->has('Retry-After'))->toBeTrue()
        ->and(Redencion::query()->count())->toBe(0);
});

it('answers 422 ya_usado to a spent code with a new key', function (): void {
    $codigo = ApiTestData::codigo($this->andres, $this->api->beneficio(1));
    $entregar = fn () => $this->postJson(
        '/api/v1/redenciones',
        ['codigo' => $codigo],
        $this->api->headers('pos-rancho', (string) Str::uuid()),
    );

    $entregar()->assertCreated();

    $entregar()
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'ya_usado');

    expect(Redencion::query()->count())->toBe(1);
});

it('replays a delivery by the signed QR or by its short code, and conflicts with another code', function (): void {
    $beneficio = $this->api->beneficio(1);
    $emitida = ApiTestData::credencialQr($this->andres, $beneficio);
    $llave = (string) Str::uuid();
    $headers = $this->api->headers('pos-rancho', $llave);

    $this->postJson('/api/v1/redenciones', ['codigo' => $emitida->token], $headers)->assertCreated();

    $this->postJson('/api/v1/redenciones', ['codigo' => $emitida->token], $this->api->headers('pos-rancho', $llave))
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true');

    $corto = mb_strtolower($emitida->credencial->codigo_corto);

    $this->postJson('/api/v1/redenciones', ['codigo' => $corto], $this->api->headers('pos-rancho', $llave))
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true');

    $otro = ApiTestData::codigo($this->andres, $this->api->beneficio(1));

    $this->postJson('/api/v1/redenciones', ['codigo' => $otro], $this->api->headers('pos-rancho', $llave))
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'redencion_en_conflicto');

    expect(Redencion::query()->count())->toBe(1);
});

it('answers a conflict to the same key with another bill amount', function (): void {
    $beneficio = $this->api->beneficio(1, [
        'tipo' => 'porcentaje',
        'valores_por_nivel' => ['bronce' => 10, 'plata' => 20, 'oro' => 30, 'diamante' => 40],
    ]);
    $body = ['tipo_documento' => 'CC', 'documento' => '1012345678', 'beneficio_id' => $beneficio->id];
    $headers = $this->api->headers('pos-rancho', (string) Str::uuid());

    $this->postJson('/api/v1/redenciones', [...$body, 'monto_cuenta_cop' => 50000], $headers)
        ->assertCreated();

    $this->postJson('/api/v1/redenciones', [...$body, 'monto_cuenta_cop' => 50000], $headers)
        ->assertOk();

    $this->postJson('/api/v1/redenciones', [...$body, 'monto_cuenta_cop' => 90000], $headers)
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'redencion_en_conflicto');

    expect(Redencion::query()->count())->toBe(1);
});

it('replays the delivery when a retry with the same key finds the code spent', function (): void {
    $codigo = ApiTestData::codigo($this->andres, $this->api->beneficio(1));
    $llave = (string) Str::uuid();

    // El otro intento con la misma llave entrega justo antes del chequeo de este: el código ya está gastado.
    $check = new class extends CheckBeneficioCredencial
    {
        public string $llave = '';

        public function __construct()
        {
            parent::__construct(app(ValidateCredencial::class), app(RevisionBeneficio::class));
        }

        public function __invoke(User $cajero, string $entrada): ChequeoBeneficio
        {
            $chequeo = parent::__invoke($cajero, $entrada);

            if ($chequeo->esValido() && $chequeo->cliente !== null && $chequeo->beneficio !== null) {
                app(RedeemBeneficio::class)($cajero, new EntregaBeneficio(
                    $chequeo->cliente->id,
                    $chequeo->beneficio->id,
                    $chequeo->credencialId,
                    null,
                    null,
                    $this->llave,
                ));
            }

            return parent::__invoke($cajero, $entrada);
        }
    };
    $check->llave = $llave;
    $this->app->instance(CheckBeneficioCredencial::class, $check);

    $repetida = $this->postJson('/api/v1/redenciones', ['codigo' => $codigo], $this->api->headers('pos-rancho', $llave))
        ->assertOk()
        ->assertJsonPath('data.referencia', $llave);

    expect($repetida->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and(Redencion::query()->count())->toBe(1);
});
