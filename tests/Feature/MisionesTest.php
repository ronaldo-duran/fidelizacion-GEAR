<?php

declare(strict_types=1);

use App\Actions\Promociones\ApproveCumplimientoMision;
use App\Actions\Promociones\RejectCumplimientoMision;
use App\Actions\Promociones\SetPromocionLimites;
use App\Actions\Promociones\SubmitCumplimientoMision;
use App\Actions\Reglas\CreateRegla;
use App\Actions\Reglas\VersionRegla;
use App\Enums\EstadoCumplimiento;
use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use App\Enums\TipoMovimiento;
use App\Enums\TipoRegla;
use App\Models\Auditoria;
use App\Models\CambioNivel;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\ComercioPromocionLimite;
use App\Models\CumplimientoMision;
use App\Models\MovimientoPuntos;
use App\Models\PromocionOtorgada;
use App\Models\Regla;
use App\Models\User;
use App\Reglas\ReglaDefinitionValidator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\LedgerTestData;
use Tests\Support\NivelesTestData;

beforeEach(function (): void {
    $fixture = (string) file_get_contents(__DIR__ . '/../Fixtures/promociones-escenarios.json');
    $this->fixture = json_decode($fixture, true);
    $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:00'));
    $this->admin = User::factory()->administradorDelGrupo()->create();
    LedgerTestData::acumulacionRegla();
    $this->cliente = LedgerTestData::cliente();

    // Los comercios del fixture (2, 3 y 4) con sus límites, creados por el grupo.
    $this->comercios = [];

    foreach ($this->fixture['limits'] as $limite) {
        $comercio = Comercio::factory()->create();
        $this->comercios[$limite['comercio_id']] = $comercio->id;
        app(SetPromocionLimites::class)(
            $this->admin,
            $comercio->id,
            $limite['permite_misiones'],
            $limite['puntos_max_por_mision'],
        );
    }

    $this->case = function (string $criterion): array {
        foreach ($this->fixture['cases'] as $case) {
            if ($case['_e3_06_criterion'] === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    };
    $this->definicion = fn (array $mision): array => [
        'comercio_id' => $this->comercios[$mision['comercio_id']],
        'titulo' => 'Misión de prueba',
        'descripcion' => 'Envía el pantallazo.',
        'puntos' => $mision['puntos'],
        'max_por_cliente' => $mision['max_por_cliente'] ?? 1,
        'requiere_soporte' => true,
    ];
    $this->mision = fn (array $mision, ?User $por = null): Regla => app(CreateRegla::class)(
        $por ?? $this->admin,
        $mision['codigo'],
        TipoRegla::Mision,
        ($this->definicion)($mision),
    );
    $this->revisor = fn (int $comercio, bool $aprueba = true): User => User::factory()
        ->cajero($this->comercios[$comercio])
        ->state(['aprueba_misiones' => $aprueba])
        ->create();
    $this->enviar = fn (string $codigo): CumplimientoMision => app(SubmitCumplimientoMision::class)(
        $this->cliente->id,
        $codigo,
        'cumplimientos/pantallazo.png',
    );
});

it('accepts the mission definitions of the fixture and rejects the invalid ones', function (): void {
    $validator = app(ReglaDefinitionValidator::class);
    $deTipo = static fn (array $casos): array => array_values(array_filter(
        $casos,
        static fn (array $caso): bool => $caso['tipo'] === TipoRegla::Mision->value,
    ));

    foreach ($deTipo($this->fixture['definitions']['valid']) as $caso) {
        expect($validator->errors($caso['tipo'], $caso['definicion']))->toBe([]);
    }

    foreach ($deTipo($this->fixture['definitions']['invalid']) as $caso) {
        expect($validator->errors($caso['tipo'], $caso['definicion']))->not->toBe([], $caso['_reason']);
    }

    $valida = $deTipo($this->fixture['definitions']['valid'])[0]['definicion'];

    expect($validator->errors('mision', [...$valida, 'descripcion' => '  ']))
        ->toBe(['La misión necesita una descripción.'])
        ->and($validator->errors('mision', [...$valida, 'condicion' => 'puntos > 10']))->not->toBe([]);
});

it('keeps each mission inside the limits the group enabled for its merchant', function (): void {
    $saves = ($this->case)('a merchant cannot exceed what the group enabled')['mission_saves'];

    foreach ($saves as $i => $save) {
        $propio = $save['by'] === 'administrador_comercio' ? $save['comercio_id'] : 2;
        $usuario = User::factory()->administradorDeComercio($this->comercios[$propio])->create();
        $guardar = fn (): Regla => ($this->mision)(
            ['codigo' => "mision-{$i}", 'comercio_id' => $save['comercio_id'], 'puntos' => $save['puntos']],
            $usuario,
        );

        match ($save['expected_result']) {
            'accepted' => expect($guardar()->tipo)->toBe(TipoRegla::Mision),
            'rejected' => expect($guardar)->toThrow(ValidationException::class),
            'forbidden' => expect($guardar)->toThrow(AuthorizationException::class),
        };
    }

    expect(Regla::query()->where('tipo', TipoRegla::Mision->value)->count())->toBe(1);
});

it('applies the merchant limits to the group administrator too and when versioning', function (): void {
    expect(fn (): Regla => ($this->mision)(['codigo' => 'grande', 'comercio_id' => 2, 'puntos' => 51]))
        ->toThrow(ValidationException::class, 'Una misión de este comercio puede dar hasta 50 puntos.');

    $mision = ($this->mision)(['codigo' => 'instagram', 'comercio_id' => 2, 'puntos' => 50]);
    $this->travel(1)->minutes();
    app(SetPromocionLimites::class)($this->admin, $this->comercios[2], true, 20);

    // Con el tope más bajo, la misión ya no se reactiva tal cual, pero sí se puede desactivar.
    expect(fn (): Regla => app(VersionRegla::class)($this->admin, $mision, $mision->definicion))
        ->toThrow(ValidationException::class, 'hasta 20 puntos');

    $inactiva = app(VersionRegla::class)($this->admin, $mision, $mision->definicion, false);

    expect($inactiva->activa)->toBeFalse()
        ->and($inactiva->version)->toBe(2);
});

it('lets a merchant administrator version only the missions of its merchant, never moving them', function (): void {
    $mision = ($this->mision)(['codigo' => 'instagram', 'comercio_id' => 2, 'puntos' => 10]);
    $propio = User::factory()->administradorDeComercio($this->comercios[2])->create();
    $ajeno = User::factory()->administradorDeComercio($this->comercios[3])->create();
    $this->travel(1)->minutes();

    expect(fn (): Regla => app(VersionRegla::class)($ajeno, $mision, $mision->definicion))
        ->toThrow(AuthorizationException::class)
        ->and(fn (): Regla => app(VersionRegla::class)(
            $propio,
            $mision,
            [...$mision->definicion, 'comercio_id' => $this->comercios[3]],
        ))
        ->toThrow(ValidationException::class, 'Una misión no cambia de comercio.');

    $nueva = app(VersionRegla::class)($propio, $mision, [...$mision->definicion, 'puntos' => 20]);

    expect($nueva->version)->toBe(2)
        ->and($nueva->definicion['puntos'])->toBe(20);
});

it('forbids a merchant administrator the registration bonus and the other rule types', function (): void {
    $usuario = User::factory()->administradorDeComercio($this->comercios[2])->create();

    expect(fn (): Regla => app(CreateRegla::class)(
        $usuario,
        'bono-lanzamiento',
        TipoRegla::BonoRegistro,
        ['puntos' => 400, 'titulo' => 'Bono de lanzamiento'],
    ))->toThrow(AuthorizationException::class)
        ->and(fn (): Regla => app(CreateRegla::class)($usuario, 'sin-comercio', TipoRegla::Mision, [
            'titulo' => 'Misión',
            'descripcion' => 'Texto',
            'puntos' => 10,
            'max_por_cliente' => 1,
            'requiere_soporte' => true,
        ]))->toThrow(ValidationException::class);
});

it('lets only the group set the promotion limits of a merchant, with an audit trail', function (): void {
    $usuario = User::factory()->administradorDeComercio($this->comercios[2])->create();

    expect(fn (): ComercioPromocionLimite => app(SetPromocionLimites::class)($usuario, $this->comercios[2], true, 500))
        ->toThrow(AuthorizationException::class)
        ->and(fn (): ComercioPromocionLimite => app(SetPromocionLimites::class)(
            $this->admin,
            $this->comercios[4],
            true,
            0,
        ))->toThrow(ValidationException::class);

    $this->actingAs($this->admin);
    $limite = app(SetPromocionLimites::class)($this->admin, $this->comercios[4], true, 30);

    expect($limite->permite_misiones)->toBeTrue()
        ->and(ComercioPromocionLimite::query()->where('comercio_id', $this->comercios[4])->count())->toBe(1)
        ->and(Auditoria::query()
            ->where('modelo', ComercioPromocionLimite::class)
            ->where('modelo_id', $limite->id)
            ->exists())->toBeTrue();
});

it('grants a mission up to its max per customer', function (): void {
    $case = ($this->case)('a mission is granted up to its max per customer');
    NivelesTestData::nivelesRegla();
    $mision = ($this->mision)($case['mission']);

    foreach ($case['submissions'] as $submission) {
        $cumplimiento = ($this->enviar)($mision->codigo);
        $revisor = ($this->revisor)($submission['reviewer']['comercio_id']);
        app(ApproveCumplimientoMision::class)($revisor, $cumplimiento->id);
    }

    $movimientos = MovimientoPuntos::query()->where('cliente_id', $this->cliente->id)->get();

    expect($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo'])
        ->and(PromocionOtorgada::query()->orderBy('ocurrencia')->pluck('ocurrencia')->all())->toBe([1, 2])
        ->and(PromocionOtorgada::query()->count())->toBe($case['expected_promociones_otorgadas'])
        ->and($movimientos->pluck('tipo')->unique()->all())->toBe([TipoMovimiento::Promocion])
        ->and($movimientos->pluck('comercio_id')->unique()->all())->toBe([$this->comercios[3]])
        ->and($movimientos->first()?->vigencia_hasta->toDateString())->toBe('2027-10-29')
        ->and(CumplimientoMision::query()->pluck('estado')->unique()->all())
        ->toBe([EstadoCumplimiento::Aprobado]);
});

it('rejects a submission beyond the max per customer', function (): void {
    $case = ($this->case)('submissions beyond the max are rejected');
    $mision = ($this->mision)($case['mission']);

    for ($i = 0; $i < $case['already_granted']; $i++) {
        app(ApproveCumplimientoMision::class)(($this->revisor)(2), ($this->enviar)($mision->codigo)->id);
    }

    expect(fn (): CumplimientoMision => ($this->enviar)($mision->codigo))
        ->toThrow(ValidationException::class, 'Ya completaste esta misión todas las veces que se puede.')
        ->and(CumplimientoMision::query()->count())->toBe($case['already_granted']);
});

it('rejects a second submission while one is pending', function (): void {
    $case = ($this->case)('one pending submission per mission and customer');
    $mision = ($this->mision)($case['mission']);

    for ($i = 0; $i < $case['pending_submissions']; $i++) {
        ($this->enviar)($mision->codigo);
    }

    // Aunque la misión haya pasado a otra versión, sigue siendo la misma misión.
    $this->travel(1)->minutes();
    app(VersionRegla::class)($this->admin, $mision, [...$mision->definicion, 'puntos' => 120]);

    expect(fn (): CumplimientoMision => ($this->enviar)($mision->codigo))
        ->toThrow(ValidationException::class, 'Ya enviaste esta misión y está en revisión.')
        ->and(CumplimientoMision::query()->count())->toBe($case['pending_submissions']);
});

it('requires the support the mission asks for and a mission in force', function (): void {
    $mision = ($this->mision)(['codigo' => 'instagram', 'comercio_id' => 2, 'puntos' => 10]);
    $enviar = app(SubmitCumplimientoMision::class);

    expect(fn (): CumplimientoMision => $enviar($this->cliente->id, 'instagram', '  '))
        ->toThrow(ValidationException::class, 'Adjunta el pantallazo que pide la misión.')
        ->and(fn (): CumplimientoMision => $enviar($this->cliente->id, 'no-existe', 'cumplimientos/x.png'))
        ->toThrow(ValidationException::class, 'Esta misión ya no está disponible.');

    // La ruta la genera el servidor dentro de `cumplimientos/`: nada fuera de esa carpeta.
    foreach (['x.png', '../.env', 'cumplimientos/../../.env', 'cumplimientos\..\.env'] as $ruta) {
        expect(fn (): CumplimientoMision => $enviar($this->cliente->id, 'instagram', $ruta))
            ->toThrow(ValidationException::class, 'El soporte no es válido.');
    }

    expect(CumplimientoMision::query()->count())->toBe(0);

    $this->travel(1)->minutes();
    $sinSoporte = app(VersionRegla::class)(
        $this->admin,
        $mision,
        [...$mision->definicion, 'requiere_soporte' => false],
    );
    $cumplimiento = $enviar($this->cliente->id, 'INSTAGRAM ', null);

    expect($cumplimiento->soporte_path)->toBeNull()
        ->and($cumplimiento->regla_id)->toBe($sinSoporte->id)
        ->and($cumplimiento->estado)->toBe(EstadoCumplimiento::Pendiente)
        ->and(MovimientoPuntos::query()->count())->toBe(0);

    $this->travel(1)->minutes();
    app(VersionRegla::class)($this->admin, $sinSoporte, $sinSoporte->definicion, false);

    expect(fn (): CumplimientoMision => $enviar(LedgerTestData::cliente('2020202020')->id, 'instagram', null))
        ->toThrow(ValidationException::class, 'Esta misión ya no está disponible.');
});

it('lets only a user of the owning merchant with the permission approve', function (): void {
    $case = ($this->case)('only a user of the owning merchant with the permission approves');
    $mision = ($this->mision)($case['mission']);

    foreach ($case['approvers'] as $i => $approver) {
        $cliente = LedgerTestData::cliente((string) (3000000000 + $i));
        $cumplimiento = app(SubmitCumplimientoMision::class)($cliente->id, $mision->codigo, 'cumplimientos/x.png');
        $usuario = match ($approver['rol']) {
            'administrador_grupo' => $this->admin,
            'administrador_comercio' => User::factory()
                ->administradorDeComercio($this->comercios[$approver['comercio_id']])
                ->apruebaMisiones()
                ->create(),
            default => ($this->revisor)($approver['comercio_id'], $approver['aprueba_misiones']),
        };
        $aprobar = fn (): MovimientoPuntos => app(ApproveCumplimientoMision::class)($usuario, $cumplimiento->id);

        if ($approver['expected_result'] === 'accepted') {
            expect($aprobar()->puntos)->toBe($case['mission']['puntos']);

            continue;
        }

        expect($aprobar)->toThrow(AuthorizationException::class)
            ->and(fn (): CumplimientoMision => app(RejectCumplimientoMision::class)($usuario, $cumplimiento->id, 'No'))
            ->toThrow(AuthorizationException::class)
            ->and($cumplimiento->fresh()?->estado)->toBe(EstadoCumplimiento::Pendiente)
            ->and($cliente->fresh()?->saldo_puntos)->toBe(0);
    }

    // Un usuario inactivo, aun con el permiso y del comercio dueño, tampoco aprueba.
    $inactivo = User::factory()->cajero($this->comercios[3])->apruebaMisiones()->inactivo()->create();
    $pendiente = CumplimientoMision::query()->where('estado', EstadoCumplimiento::Pendiente->value)->firstOrFail();

    expect(fn (): MovimientoPuntos => app(ApproveCumplimientoMision::class)($inactivo, $pendiente->id))
        ->toThrow(AuthorizationException::class);
});

it('records the reason and the reviewer of a rejection and grants nothing', function (): void {
    $case = ($this->case)('a rejection records the reason and grants nothing');
    $mision = ($this->mision)($case['mission']);
    $submission = $case['submissions'][0];
    $revisor = ($this->revisor)($submission['reviewer']['comercio_id']);
    $cumplimiento = ($this->enviar)($mision->codigo);

    expect(fn (): CumplimientoMision => app(RejectCumplimientoMision::class)($revisor, $cumplimiento->id, '   '))
        ->toThrow(ValidationException::class, 'Escribe por qué se rechaza')
        ->and(fn (): CumplimientoMision => app(RejectCumplimientoMision::class)(
            $revisor,
            $cumplimiento->id,
            str_repeat('a', 256),
        ))
        ->toThrow(ValidationException::class, 'El motivo no puede pasar de 255 caracteres.')
        ->and($cumplimiento->fresh()?->estado)->toBe(EstadoCumplimiento::Pendiente);

    $rechazado = app(RejectCumplimientoMision::class)($revisor, $cumplimiento->id, " {$submission['motivo_rechazo']} ");

    expect($rechazado->estado)->toBe(EstadoCumplimiento::from($submission['estado_final']))
        ->and($rechazado->motivo_rechazo)->toBe($submission['motivo_rechazo'])
        ->and($rechazado->revisado_por)->toBe($revisor->id)
        ->and($rechazado->revisado_at?->toDateTimeString())->toBe('2026-10-29 10:00:00')
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe($case['expected_saldo'])
        ->and(PromocionOtorgada::query()->count())->toBe($case['expected_promociones_otorgadas'])
        ->and(MovimientoPuntos::query()->count())->toBe(0)
        // Un rechazo no cuenta para el máximo: el cliente puede volver a enviarla.
        ->and(($this->enviar)($mision->codigo)->estado)->toBe(EstadoCumplimiento::Pendiente);
});

it('does not approve while no accrual rule is in force and keeps the submission pending', function (): void {
    $mision = ($this->mision)(['codigo' => 'instagram', 'comercio_id' => 2, 'puntos' => 10]);
    $cumplimiento = ($this->enviar)($mision->codigo);
    $acumulacion = Regla::query()->where('tipo', TipoRegla::Acumulacion->value)->whereNull('vigente_hasta')->sole();
    // El motor valida que las categorías de la regla existan antes de versionarla.
    Categoria::factory()->create(['slug' => 'restaurante']);
    $this->travel(1)->minutes();
    app(VersionRegla::class)($this->admin, $acumulacion, $acumulacion->definicion, false);

    // Sin regla de acumulación no se sabe cuándo vencen los puntos: no se abona un lote sin vigencia.
    expect(fn (): MovimientoPuntos => app(ApproveCumplimientoMision::class)(($this->revisor)(2), $cumplimiento->id))
        ->toThrow(ValidationException::class, 'No hay una regla de acumulación vigente')
        ->and($cumplimiento->fresh()?->estado)->toBe(EstadoCumplimiento::Pendiente)
        ->and(MovimientoPuntos::query()->count())->toBe(0)
        ->and(PromocionOtorgada::query()->count())->toBe(0)
        ->and($this->cliente->fresh()?->saldo_puntos)->toBe(0);
});

it('grants the mission version of the submission', function (): void {
    $case = ($this->case)('approval uses the mission version of the submission');
    [$v1, $v2] = $case['mission_versions'];
    $mision = ($this->mision)($v1);
    $cumplimiento = ($this->enviar)($mision->codigo);
    $this->travel(1)->minutes();
    $nueva = app(VersionRegla::class)($this->admin, $mision, ($this->definicion)($v2));
    $this->travel(1)->minutes();
    app(VersionRegla::class)($this->admin, $nueva, $nueva->definicion, false);

    $movimiento = app(ApproveCumplimientoMision::class)(($this->revisor)(2), $cumplimiento->id);
    $esperado = $case['expected_movimientos'][0];

    expect($movimiento->tipo)->toBe(TipoMovimiento::from($esperado['tipo']))
        ->and($movimiento->puntos)->toBe($esperado['puntos'])
        ->and($movimiento->regla_id)->toBe($mision->id)
        ->and($movimiento->regla_version)->toBe($esperado['regla_version'])
        ->and($movimiento->comercio_id)->toBe($this->comercios[$esperado['comercio_id']]);
});

it('reviews a submission only once', function (): void {
    $mision = ($this->mision)(['codigo' => 'matricula', 'comercio_id' => 3, 'puntos' => 100, 'max_por_cliente' => 3]);
    $revisor = ($this->revisor)(3);
    $cumplimiento = ($this->enviar)($mision->codigo);
    app(ApproveCumplimientoMision::class)($revisor, $cumplimiento->id);

    expect(fn (): MovimientoPuntos => app(ApproveCumplimientoMision::class)($revisor, $cumplimiento->id))
        ->toThrow(ValidationException::class, 'Este cumplimiento ya fue revisado.')
        ->and(fn (): CumplimientoMision => app(RejectCumplimientoMision::class)($revisor, $cumplimiento->id, 'Tarde'))
        ->toThrow(ValidationException::class, 'Este cumplimiento ya fue revisado.')
        ->and(MovimientoPuntos::query()->count())->toBe(1)
        ->and(fn (): bool => $cumplimiento->fresh()?->update(['soporte_path' => 'otro.png']) ?? false)
        ->toThrow(LogicException::class)
        ->and(fn (): ?bool => $cumplimiento->fresh()?->delete())->toThrow(LogicException::class);
});

it('keeps one grant per occurrence in the database', function (): void {
    $mision = ($this->mision)(['codigo' => 'matricula', 'comercio_id' => 3, 'puntos' => 100, 'max_por_cliente' => 3]);
    $movimiento = app(ApproveCumplimientoMision::class)(($this->revisor)(3), ($this->enviar)($mision->codigo)->id);

    // Una segunda aprobación que leyera el mismo conteo tomaría la ocurrencia 1 otra vez: la base la rechaza.
    expect(fn (): PromocionOtorgada => PromocionOtorgada::query()->create([
        'cliente_id' => $this->cliente->id,
        'codigo' => $mision->codigo,
        'ocurrencia' => 1,
        'movimiento_id' => $movimiento->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('raises the level in the same request when an approved mission crosses a threshold', function (): void {
    NivelesTestData::nivelesRegla();
    $mision = ($this->mision)(['codigo' => 'matricula', 'comercio_id' => 3, 'puntos' => 200, 'max_por_cliente' => 3]);

    for ($i = 0; $i < 3; $i++) {
        app(ApproveCumplimientoMision::class)(($this->revisor)(3), ($this->enviar)($mision->codigo)->id);
    }

    expect($this->cliente->fresh()?->nivel_efectivo)->toBe(Nivel::Plata)
        ->and(CambioNivel::query()->where('cliente_id', $this->cliente->id)->sole()->motivo)
        ->toBe(MotivoCambioNivel::AscensoPromocion);
});

it('keeps missions out of the purchase simulator and the rules screen', function (): void {
    ($this->mision)(['codigo' => 'instagram', 'comercio_id' => 2, 'puntos' => 10]);
    $this->actingAs($this->admin);

    expect(TipoRegla::Mision->appliesToPurchases())->toBeFalse()
        ->and(App\Filament\Resources\Reglas\ReglaResource::getEloquentQuery()->pluck('codigo')->all())
        ->not->toContain('instagram');
});
