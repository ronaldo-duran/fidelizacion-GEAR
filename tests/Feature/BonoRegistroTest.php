<?php

declare(strict_types=1);

use App\Actions\Clientes\CompleteRegistro;
use App\Actions\Clientes\StartRegistro;
use App\Actions\Promociones\GrantBonoRegistro;
use App\Actions\Reglas\CreateRegla;
use App\Actions\Reglas\VersionRegla;
use App\Clientes\DatosRegistro;
use App\Enums\CanalRegistro;
use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use App\Enums\TipoDocumento;
use App\Enums\TipoMovimiento;
use App\Enums\TipoRegla;
use App\Models\CambioNivel;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\PromocionOtorgada;
use App\Models\Regla;
use App\Models\User;
use App\Reglas\ReglaDefinitionValidator;
use App\Sms\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\LedgerTestData;
use Tests\Support\NivelesTestData;

beforeEach(function (): void {
    $this->fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/promociones-escenarios.json'), true);
    $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:00'));
    $this->admin = User::factory()->administradorDelGrupo()->create();
    LedgerTestData::acumulacionRegla();
    $this->case = function (string $criterion): array {
        foreach ($this->fixture['cases'] as $case) {
            if ($case['_e3_06_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    };
    $this->bono = fn (string $codigo, int $puntos, string $titulo = 'Bono de lanzamiento'): Regla => app(CreateRegla::class)(
        $this->admin,
        $codigo,
        TipoRegla::BonoRegistro,
        ['puntos' => $puntos, 'titulo' => $titulo],
    );
    // Registro completo por las Actions de E8-02, con el código del SMS.
    $this->registrar = function (string $documento = '1023456789'): Cliente {
        $pendiente = app(StartRegistro::class)(new DatosRegistro(
            TipoDocumento::CC,
            $documento,
            'Marcela Gómez',
            CarbonImmutable::today()->subYears(30),
            '3001234567',
            null,
            '4826',
            CanalRegistro::Pwa,
            '10.0.0.1',
        ));
        $enviados = app(SmsSender::class)->enviados();
        preg_match('/\b(\d{6})\b/', end($enviados)['mensaje'] ?? '', $match);

        return app(CompleteRegistro::class)($pendiente->id, $match[1] ?? '');
    };
});

it('accepts the registration bonus definitions of the fixture and rejects the invalid ones', function (): void {
    $validator = app(ReglaDefinitionValidator::class);
    $deTipo = static fn (array $casos): array => array_values(array_filter(
        $casos,
        static fn (array $caso): bool => $caso['tipo'] === TipoRegla::BonoRegistro->value,
    ));

    foreach ($deTipo($this->fixture['definitions']['valid']) as $caso) {
        expect($validator->errors($caso['tipo'], $caso['definicion']))->toBe([]);
    }

    foreach ($deTipo($this->fixture['definitions']['invalid']) as $caso) {
        expect($validator->errors($caso['tipo'], $caso['definicion']))->not->toBe([], $caso['_reason']);
    }

    expect($validator->errors('bono_registro', ['puntos' => 400, 'titulo' => '   ']))
        ->toBe(['El bono necesita un título.']);
});

it('grants the registration bonus through the ledger', function (): void {
    $case = ($this->case)('registration bonus is granted through the ledger');
    $regla = $case['active_rules'][0];
    $bono = ($this->bono)($regla['codigo'], $regla['puntos']);

    $cliente = ($this->registrar)();

    $esperado = $case['expected_movimientos'][0];
    $movimiento = MovimientoPuntos::query()->where('cliente_id', $cliente->id)->sole();

    expect($movimiento->tipo)->toBe(TipoMovimiento::from($esperado['tipo']))
        ->and($movimiento->puntos)->toBe($esperado['puntos'])
        ->and($movimiento->regla_id)->toBe($bono->id)
        ->and($movimiento->regla_version)->toBe($esperado['regla_version'])
        ->and($movimiento->comercio_id)->toBe($esperado['comercio_id'])
        ->and($movimiento->vigencia_hasta->toDateString())->toBe('2027-10-29')
        ->and($cliente->saldo_puntos)->toBe($case['expected_saldo'])
        ->and(PromocionOtorgada::query()->sole()->only(['cliente_id', 'codigo', 'ocurrencia', 'movimiento_id']))
        ->toBe([
            'cliente_id' => $cliente->id,
            'codigo' => $regla['codigo'],
            'ocurrencia' => 1,
            'movimiento_id' => $movimiento->id,
        ]);
});

it('grants nothing without a registration bonus in force', function (): void {
    $case = ($this->case)('no bonus without a valid rule');
    $bono = ($this->bono)('bono-lanzamiento', 400);
    $this->travel(1)->minutes();
    app(VersionRegla::class)($this->admin, $bono, $bono->definicion, false);
    $this->travel(1)->minutes();

    $cliente = ($this->registrar)();

    expect(MovimientoPuntos::query()->where('cliente_id', $cliente->id)->count())
        ->toBe(count($case['expected_movimientos']))
        ->and($cliente->saldo_puntos)->toBe($case['expected_saldo'])
        ->and(PromocionOtorgada::query()->count())->toBe(0);
});

it('grants the registration bonus once, enforced by the database', function (): void {
    $case = ($this->case)('the registration bonus is granted once, enforced by the database');
    $regla = $case['active_rules'][0];
    ($this->bono)($regla['codigo'], $regla['puntos']);
    $cliente = LedgerTestData::cliente();
    $grant = app(GrantBonoRegistro::class);

    // El segundo abono no consulta antes: inserta, choca con la restricción única y se deshace completo.
    $resultados = array_map(static fn (): ?MovimientoPuntos => $grant($cliente->id), $case['concurrent_actions']);

    expect($resultados[0])->toBeInstanceOf(MovimientoPuntos::class)
        ->and($resultados[1])->toBeNull()
        ->and(PromocionOtorgada::query()->count())->toBe($case['expected_promociones_otorgadas'])
        ->and(MovimientoPuntos::query()->where('cliente_id', $cliente->id)->count())->toBe(1)
        ->and($cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo']);
});

it('raises the level in the same request when the bonus crosses a threshold', function (): void {
    NivelesTestData::nivelesRegla();
    ($this->bono)('bono-lanzamiento', 600);

    $cliente = ($this->registrar)();

    expect($cliente->nivel_efectivo)->toBe(Nivel::Plata)
        ->and(CambioNivel::query()->where('cliente_id', $cliente->id)->sole()->motivo)
        ->toBe(MotivoCambioNivel::AscensoPromocion);
});

it('rejects a second registration bonus in force', function (): void {
    $case = ($this->case)('no two valid bono_registro rules');
    $activa = $case['active_rules'][0];
    $bono = ($this->bono)($activa['codigo'], $activa['puntos']);
    $nueva = $case['new_rule'];

    expect(fn (): Regla => ($this->bono)($nueva['codigo'], $nueva['puntos'], 'Bono de Navidad'))
        ->toThrow(ValidationException::class, 'Ya rige otro bono de registro («bono-lanzamiento»)');

    // Desactivado el primero, el segundo sí se crea; reactivar el primero choca con el segundo.
    $this->travel(1)->minutes();
    $inactivo = app(VersionRegla::class)($this->admin, $bono, $bono->definicion, false);
    $this->travel(1)->minutes();
    $navidad = ($this->bono)($nueva['codigo'], $nueva['puntos'], 'Bono de Navidad');
    $this->travel(1)->minutes();

    expect($navidad->vigente_hasta)->toBeNull()
        ->and(fn (): Regla => app(VersionRegla::class)($this->admin, $inactivo, $bono->definicion))
        ->toThrow(ValidationException::class, 'Ya rige otro bono de registro («bono-navidad»)');
});

it('keeps the registration bonus out of the purchase simulator', function (): void {
    expect(TipoRegla::BonoRegistro->appliesToPurchases())->toBeFalse()
        ->and(TipoRegla::Acumulacion->appliesToPurchases())->toBeTrue()
        ->and(TipoRegla::Bonificacion->appliesToPurchases())->toBeTrue()
        ->and(TipoRegla::Niveles->appliesToPurchases())->toBeFalse();
});
