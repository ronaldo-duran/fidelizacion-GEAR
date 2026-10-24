<?php

declare(strict_types=1);

use App\Actions\Credenciales\IssueCredencial;
use App\Actions\Credenciales\ValidateCredencial;
use App\Credenciales\CredencialEmitida;
use App\Credenciales\QrRenderer;
use App\Credenciales\ValidacionCredencial;
use App\Enums\PropositoCredencial;
use App\Enums\ResultadoCredencial;
use App\Enums\TipoDocumento;
use App\Jobs\Nocturno\PurgeCredenciales;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\CredencialQr;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

beforeEach(function (): void {
    $this->fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/qr-escenarios.json'), true);
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00'));
    $this->comercio = Comercio::factory()->create(['razon_social' => 'El Rancho de Javi']);
    $this->otroComercio = Comercio::factory()->create(['razon_social' => 'Cervecería BBC']);
    $this->cajero = User::factory()->cajero($this->comercio->id)->create();
    $this->cliente = Cliente::query()->create(['tipo_documento' => TipoDocumento::CC, 'documento' => '1010101010']);
    $this->issue = fn (string $purpose, string $comercio = 'own'): CredencialEmitida => app(IssueCredencial::class)(
        $this->cliente,
        PropositoCredencial::from($purpose),
        $purpose === 'beneficio' ? 7 : null,
        match (true) {
            $purpose !== 'beneficio' => null,
            $comercio === 'own' => $this->comercio->id,
            default => $this->otroComercio->id,
        },
    );
    $this->validate = fn (string $entrada, string $purpose): ValidacionCredencial => app(ValidateCredencial::class)(
        $this->cajero,
        $entrada,
        PropositoCredencial::from($purpose),
    );
    $this->case = function (string $criterion): array {
        foreach ($this->fixture['cases'] as $case) {
            if ($case['_e4_04_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    };
});

it('keeps the timing and limits in config equal to the fixture', function (): void {
    expect(config('credenciales.ttl_seconds'))->toBe($this->fixture['timing']['ttl_seconds']['value'])
        ->and(config('credenciales.refresh_seconds'))->toBe($this->fixture['timing']['refresh_seconds']['value'])
        ->and(config('credenciales.clock_skew_seconds'))->toBe($this->fixture['timing']['clock_skew_seconds']['value'])
        ->and(config('credenciales.identify_prefix'))->toBe($this->fixture['short_code']['identify_prefix'])
        ->and(config('credenciales.max_failed_short_codes_per_minute'))
        ->toBe($this->fixture['short_code']['max_failed_attempts_per_minute']['value']);
});

it('validates each fixture timeline', function (string $criterion): void {
    $case = ($this->case)($criterion);
    $start = CarbonImmutable::now();
    $emitida = ($this->issue)($case['issue']['purpose'], $case['issue']['comercio'] ?? 'own');

    foreach ($case['validations'] as $validation) {
        $this->travelTo($start->addSeconds($validation['at']));
        $resultado = ($this->validate)($emitida->token, $validation['as_purpose'] ?? $case['issue']['purpose']);

        $esperado = ['valid' => 'valido', 'expired' => 'vencido', 'used' => 'usado', 'invalid' => 'invalido'];

        expect($resultado->resultado->value)->toBe(
            $esperado[$validation['expected']],
            "{$case['_description']} at {$validation['at']} s",
        );
    }
})->with([
    'identify window' => ['a valid identify code works within its window'],
    'expiry' => ['an expired code is rejected'],
    'single use benefit' => ['a benefit code is single use'],
    'benefit of another merchant' => ['a benefit code belongs to its merchant'],
    'purpose bound' => ['codes are bound to their purpose'],
]);

it('rejects a tampered token and reveals no customer data', function (string $part): void {
    $emitida = ($this->issue)('identificar');
    [$body, $signature] = explode('.', $emitida->token);
    $payload = json_decode((string) base64_decode(strtr($body, '-_', '+/'), true), true);
    $otro = Cliente::query()->create(['tipo_documento' => TipoDocumento::CC, 'documento' => '2020202020']);

    $tampered = match ($part) {
        'signature' => $body . '.' . strrev($signature),
        default => rtrim(strtr(base64_encode((string) json_encode([
            ...$payload,
            ...($part === 'cliente_id' ? ['c' => $otro->id] : ['p' => 'beneficio']),
        ])), '+/', '-_'), '=') . '.' . $signature,
    };

    $resultado = ($this->validate)($tampered, 'identificar');

    expect($resultado->resultado)->toBe(ResultadoCredencial::Invalido)
        ->and($resultado->cliente)->toBeNull()
        ->and($resultado->credencial)->toBeNull();
})->with(['signature', 'cliente_id', 'purpose']);

it('never issues the same token or short code twice', function (): void {
    $first = ($this->issue)('identificar');
    $this->travel($this->fixture['timing']['refresh_seconds']['value'])->seconds();
    $second = ($this->issue)('identificar');

    expect($first->token)->not->toBe($second->token)
        ->and($first->codigoCorto)->not->toBe($second->codigoCorto)
        ->and($first->codigoCorto)->toMatch('/' . $this->fixture['short_code']['format'] . '/');
});

it('accepts the short code like the QR and locks the cashier after too many failures', function (): void {
    $emitida = ($this->issue)('identificar');

    expect(($this->validate)(strtolower($emitida->codigoCorto), 'identificar')->cliente?->id)->toBe($this->cliente->id);

    $attempts = ($this->case)('short code with attempt limit')['short_code_attempts'];
    $wrongCodes = array_filter($attempts, static fn (string $code): bool => $code !== 'issued');

    foreach ($wrongCodes as $wrong) {
        // A wrong code that happens to be the issued one is swapped for another wrong one.
        ($this->validate)($wrong === $emitida->codigoCorto ? 'AR-9999X' : $wrong, 'identificar');
    }

    expect(($this->validate)($emitida->codigoCorto, 'identificar')->resultado)->toBe(ResultadoCredencial::Bloqueado);

    // The lockout lasts one minute ("max_failed_attempts_per_minute").
    $this->travel(1)->minutes();
    $this->travel(1)->seconds();
    $nueva = ($this->issue)('identificar');

    expect(($this->validate)($nueva->codigoCorto, 'identificar')->esValida())->toBeTrue();
});

it('retries a short code taken by a valid credential and frees it once that one expires', function (): void {
    // Same seed, same sequence of short codes: the second issue draws the taken code first.
    $seeded = static fn (): Randomizer => new Randomizer(new Mt19937(7));
    $sequence = $seeded();
    $firstCode = sprintf('AR-%04d', $sequence->getInt(0, 9999));
    $secondCode = sprintf('AR-%04d', $sequence->getInt(0, 9999));
    $otroCliente = Cliente::query()->create(['tipo_documento' => TipoDocumento::CE, 'documento' => 'E123456']);

    app()->instance(Randomizer::class, $seeded());
    $first = ($this->issue)('identificar');
    app()->instance(Randomizer::class, $seeded());
    $second = app(IssueCredencial::class)($otroCliente, PropositoCredencial::Identificar);

    expect($first->codigoCorto)->toBe($firstCode)
        ->and($second->codigoCorto)->toBe($secondCode)
        ->and(($this->validate)($firstCode, 'identificar')->cliente?->id)->toBe($this->cliente->id)
        ->and(($this->validate)($secondCode, 'identificar')->cliente?->id)->toBe($otroCliente->id);

    // A code stays reserved until its last window ends: at most two windows (validity + skew) after issue.
    $timing = $this->fixture['timing'];
    $window = $timing['ttl_seconds']['value'] + $timing['clock_skew_seconds']['value'];
    $this->travel((2 * $window) + 1)->seconds();
    app()->instance(Randomizer::class, $seeded());

    expect(app(IssueCredencial::class)($otroCliente, PropositoCredencial::Identificar)->codigoCorto)->toBe($firstCode);
});

it('keeps a short code unique in the database, not only in code', function (): void {
    $emitida = ($this->issue)('identificar');
    $reservas = DB::table('credenciales_qr_codigos')->where('credencial_qr_id', $emitida->credencial->id)->get();

    expect($reservas)->not->toBeEmpty()
        ->and(fn () => DB::table('credenciales_qr_codigos')->insert([
            'credencial_qr_id' => $emitida->credencial->id,
            'codigo_corto' => $emitida->codigoCorto,
            'ventana' => $reservas->first()?->ventana,
        ]))->toThrow(UniqueConstraintViolationException::class);
});

it('gives a benefit short code the prefix of its merchant', function (): void {
    expect(($this->issue)('beneficio')->codigoCorto)->toStartWith('RAN-')
        ->and(($this->issue)('beneficio', 'other')->codigoCorto)->toStartWith('CER-');
});

it('accepts a benefit code only once even for two cashiers of the merchant', function (): void {
    $emitida = ($this->issue)('beneficio');
    $otroCajero = User::factory()->cajero($this->comercio->id)->create();

    $first = ($this->validate)($emitida->token, 'beneficio');
    $second = app(ValidateCredencial::class)($otroCajero, $emitida->token, PropositoCredencial::Beneficio);

    expect($first->esValida())->toBeTrue()
        ->and($second->resultado)->toBe(ResultadoCredencial::Usado)
        ->and(CredencialQr::query()->sole()->usado_por)->toBe($this->cajero->id);
});

it('refuses an inactive cashier', function (): void {
    $this->cajero->forceFill(['activo' => false])->save();

    expect(fn (): ValidacionCredencial => ($this->validate)(($this->issue)('identificar')->token, 'identificar'))
        ->toThrow(AuthorizationException::class);
});

it('draws the token as a real QR image', function (): void {
    $svg = app(QrRenderer::class)->svg(($this->issue)('identificar')->token);

    expect($svg)->toStartWith('<?xml')->toContain('<svg');
});

it('purges expired credentials at night', function (): void {
    ($this->issue)('identificar');
    $this->travelTo(CarbonImmutable::parse('2026-10-24 01:30:00'));
    $vigente = ($this->issue)('identificar');

    Bus::dispatchSync(new PurgeCredenciales('2026-10-24'));

    expect(CredencialQr::query()->pluck('id')->all())->toBe([$vigente->credencial->id]);
});
