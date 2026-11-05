<?php

declare(strict_types=1);

use App\Enums\EstadoPago;
use App\Models\PagoMembresia;
use App\Pagos\FirmaInvalida;
use App\Pagos\WompiPasarela;
use Illuminate\Http\Request;
use Tests\Support\MembresiasTestData;

// Vectores de firma del adaptador de Wompi (E6-01).

beforeEach(function (): void {
    MembresiasTestData::configurarWompi();
});

$request = static fn (array $evento): Request => Request::create(
    '/webhooks/pagos/wompi',
    'POST',
    server: ['CONTENT_TYPE' => 'application/json'],
    content: json_encode($evento, JSON_THROW_ON_ERROR),
);

it('signs the checkout with the integrity vector of the Wompi documentation', function (): void {
    // docs.wompi.co, «Widget & Checkout Web», ejemplo de firma de integridad sin expiración.
    expect(WompiPasarela::integrity(
        'sk8-438k4-xmxm392-sn2m',
        2490000,
        'prod_integrity_Z5mMke9x0k8gpErbDqwrJXMqsI6SFli6',
    ))->toBe('37c8407747e595535433ef8f6a811d853cd943046624a0ec04662b17bbf33bf5');
});

it('computes the event checksum from the listed properties, the timestamp and the secret', function (): void {
    // Cadena del ejemplo de docs.wompi.co, «Eventos». El checksum que publica la documentación no
    // corresponde a esa cadena, así que el esperado es el SHA256 calculado aparte (sha256sum).
    $evento = [
        'data' => ['transaction' => [
            'id' => '1234-1610641025-49201',
            'status' => 'APPROVED',
            'amount_in_cents' => 4490000,
        ]],
        'signature' => ['properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents']],
        'timestamp' => 1530291411,
    ];

    expect(WompiPasarela::checksum($evento, 'prod_events_OcHnIzeBl5socpwByQ4hA52Em3USQ93Z'))
        ->toBe('5a18ec5e8fdb7df463e9f94774cba8f583ba21bd04a09ceff2ea68a4bc0aefbe');
});

it('builds the checkout URL in cents with the integrity signature', function (): void {
    $pago = app(PagoMembresia::class);
    $pago->forceFill(['referencia' => 'MREF1', 'monto_cop' => 49900]);

    $url = app(WompiPasarela::class)->checkout($pago, 'https://club.test/membresia/pago/MREF1');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://checkout.wompi.co/p/?')
        ->and($query)->toMatchArray([
            'public-key' => 'pub_test_llave',
            'currency' => 'COP',
            'amount-in-cents' => '4990000',
            'reference' => 'MREF1',
            'signature:integrity' => WompiPasarela::integrity('MREF1', 4990000, MembresiasTestData::SECRETO_INTEGRIDAD),
            'redirect-url' => 'https://club.test/membresia/pago/MREF1',
        ]);
});

it('translates a signed event into the notification in whole pesos', function () use ($request): void {
    $notificacion = app(WompiPasarela::class)->notificacion(
        $request(MembresiasTestData::evento('MREF1', 'aprobado', 49900)),
    );

    expect($notificacion->referencia)->toBe('MREF1')
        ->and($notificacion->idExterno)->toBe('12345-1700000000-10001')
        ->and($notificacion->estado)->toBe(EstadoPago::Aprobado)
        ->and($notificacion->estadoInformado)->toBe('APPROVED')
        ->and($notificacion->montoCop)->toBe(49900)
        ->and($notificacion->moneda)->toBe('COP');
});

it('maps the Wompi statuses', function (string $status, ?EstadoPago $estado) use ($request): void {
    $evento = MembresiasTestData::evento('MREF1', $status, 49900);
    $notificacion = app(WompiPasarela::class)->notificacion($request($evento));

    expect($notificacion->estado)->toBe($estado);
})->with([
    ['APPROVED', EstadoPago::Aprobado],
    ['DECLINED', EstadoPago::Rechazado],
    ['ERROR', EstadoPago::Rechazado],
    ['VOIDED', EstadoPago::Anulado],
    ['PENDING', EstadoPago::Pendiente],
    ['SOMETHING_NEW', null],
]);

it('leaves the amount empty when it has a fraction of a peso', function () use ($request): void {
    $evento = MembresiasTestData::evento('MREF1', 'aprobado', 49900);
    $evento['data']['transaction']['amount_in_cents'] = 4990050;
    $evento['signature']['checksum'] = WompiPasarela::checksum($evento, MembresiasTestData::SECRETO_EVENTOS);

    expect(app(WompiPasarela::class)->notificacion($request($evento))->montoCop)->toBeNull();
});

it('rejects an event with a wrong signature, a tampered amount or no secret configured', function (
    Closure $alterar,
) use ($request): void {
    $evento = MembresiasTestData::evento('MREF1', 'aprobado', 49900);
    $evento = $alterar($evento);

    expect(fn () => app(WompiPasarela::class)->notificacion($request($evento)))->toThrow(FirmaInvalida::class);
})->with([
    'firma falsa' => [
        static fn (): array => MembresiasTestData::evento('MREF1', 'aprobado', 49900, firmaValida: false),
    ],
    'monto alterado' => [static function (array $evento): array {
        $evento['data']['transaction']['amount_in_cents'] = 100;

        return $evento;
    }],
    'sin propiedades' => [static function (array $evento): array {
        $evento['signature']['properties'] = [];

        return $evento;
    }],
    'sin secreto' => [static function (array $evento): array {
        config(['pagos.wompi.secreto_eventos' => '']);

        return $evento;
    }],
]);
