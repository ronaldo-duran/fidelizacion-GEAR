<?php

declare(strict_types=1);

use App\Actions\Niveles\CloseEventoCalificado;
use App\Actions\Niveles\ReevaluateNivelEfectivo;
use App\Actions\Niveles\RegisterEventoCalificado;
use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use App\Enums\TipoEventoCalificado;
use App\Models\Auditoria;
use App\Models\CambioNivel;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\EventoCalificado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\NivelesTestData;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 10:00:00'));
    NivelesTestData::nivelesRegla();
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->colegio = Comercio::factory()->create([
        'categoria_id' => Categoria::query()->create(['nombre' => 'Colegio', 'slug' => 'colegio'])->id,
    ]);
    $this->inmobiliaria = Comercio::factory()->create();
    $this->cliente = NivelesTestData::cliente([]);
    $this->register = fn (
        User $user,
        TipoEventoCalificado $tipo,
        Comercio $comercio,
        ?string $hasta = '2027-06-30',
        string $soporte = 'soportes/evento.pdf',
    ): EventoCalificado => app(RegisterEventoCalificado::class)(
        $user,
        $this->cliente->id,
        $comercio->id,
        $tipo,
        CarbonImmutable::parse('2026-10-17'),
        $hasta === null ? null : CarbonImmutable::parse($hasta),
        $soporte,
    );
});

it('registers a property purchase for the configured months and promotes right away', function (): void {
    $evento = ($this->register)($this->admin, TipoEventoCalificado::CompraInmueble, $this->inmobiliaria, null);
    $cliente = $this->cliente->fresh();

    expect($evento->vigente_hasta->toDateString())->toBe('2027-10-17')
        ->and($evento->registrado_por)->toBe($this->admin->id)
        ->and($cliente?->nivel_efectivo)->toBe(Nivel::Diamante)
        ->and($cliente?->nivel_por_evento)->toBe(Nivel::Diamante)
        ->and($cliente?->evento_vigente_hasta?->toDateString())->toBe('2027-10-17')
        ->and(CambioNivel::query()->sole()->motivo)->toBe(MotivoCambioNivel::AscensoEvento);
});

it('asks for the end date of a school enrollment', function (): void {
    expect(fn (): EventoCalificado => ($this->register)(
        $this->admin,
        TipoEventoCalificado::MatriculaColegio,
        $this->colegio,
        null,
    ))->toThrow(ValidationException::class, 'hasta cuándo va la matrícula');
});

it('rejects invalid events', function (string $case, string $message): void {
    $register = match ($case) {
        'enrollment outside a school' => fn (): EventoCalificado => ($this->register)(
            $this->admin,
            TipoEventoCalificado::MatriculaColegio,
            $this->inmobiliaria,
        ),
        'no support file' => fn (): EventoCalificado => ($this->register)(
            $this->admin,
            TipoEventoCalificado::CompraInmueble,
            $this->inmobiliaria,
            null,
            ' ',
        ),
        default => fn (): EventoCalificado => ($this->register)(
            $this->admin,
            TipoEventoCalificado::MatriculaColegio,
            $this->colegio,
            '2026-10-01',
        ),
    };

    expect($register)->toThrow(ValidationException::class, $message)
        ->and(EventoCalificado::query()->count())->toBe(0);
})->with([
    ['enrollment outside a school', 'solo se registran en un colegio'],
    ['no support file', 'Adjunta el soporte'],
    ['end before start', 'no puede ser anterior'],
]);

it('authorizes each event type by role and merchant', function (string $rol, string $tipo, bool $allowed): void {
    $user = match ($rol) {
        'group admin' => $this->admin,
        'school admin' => User::factory()->administradorDeComercio($this->colegio->id)->create(),
        'other merchant admin' => User::factory()->administradorDeComercio($this->inmobiliaria->id)->create(),
        default => User::factory()->cajero($this->colegio->id)->create(),
    };
    $tipo = TipoEventoCalificado::from($tipo);
    $comercio = $tipo === TipoEventoCalificado::MatriculaColegio ? $this->colegio : $this->inmobiliaria;
    $register = fn (): EventoCalificado => ($this->register)($user, $tipo, $comercio);

    $allowed
        ? expect($register())->toBeInstanceOf(EventoCalificado::class)
        : expect($register)->toThrow(AuthorizationException::class);
})->with([
    ['group admin', 'compra_inmueble', true],
    ['group admin', 'matricula_colegio', true],
    ['school admin', 'matricula_colegio', true],
    ['school admin', 'compra_inmueble', false],
    ['other merchant admin', 'matricula_colegio', false],
    ['cashier', 'matricula_colegio', false],
]);

it('closes an enrollment, audits it and lowers the level only at night', function (): void {
    $matriculas = array_map(
        fn (string $hasta): EventoCalificado => ($this->register)(
            $this->admin,
            TipoEventoCalificado::MatriculaColegio,
            $this->colegio,
            $hasta,
        ),
        ['2027-03-31', '2027-06-30', '2027-06-30'],
    );
    expect($this->cliente->fresh()?->nivel_efectivo)->toBe(Nivel::Diamante);

    $cerrada = app(CloseEventoCalificado::class)(
        $this->admin,
        $matriculas[0]->id,
        CarbonImmutable::today(),
        'Se retiró',
    );

    expect($cerrada->vigente_hasta->toDateString())->toBe('2026-10-17')
        ->and($cerrada->cerrado_por)->toBe($this->admin->id)
        ->and($this->cliente->fresh()?->nivel_efectivo)->toBe(Nivel::Diamante)
        ->and(Auditoria::query()->where('modelo', EventoCalificado::class)->where('evento', 'actualizado')->count())
        ->toBe(1);

    $this->travel(1)->days();
    app(ReevaluateNivelEfectivo::class)($this->cliente->id);

    expect($this->cliente->fresh()?->nivel_efectivo)->toBe(Nivel::Oro);
});

it('rejects a second close, a close without reason and a date outside the event', function (): void {
    $evento = ($this->register)($this->admin, TipoEventoCalificado::MatriculaColegio, $this->colegio);
    $close = fn (string $fecha, string $motivo): EventoCalificado => app(CloseEventoCalificado::class)(
        $this->admin,
        $evento->id,
        CarbonImmutable::parse($fecha),
        $motivo,
    );

    expect(fn (): EventoCalificado => $close('2026-11-01', ' '))
        ->toThrow(ValidationException::class, 'motivo')
        ->and(fn (): EventoCalificado => $close('2027-07-01', 'Retiro'))
        ->toThrow(ValidationException::class, 'vigencia')
        ->and(fn (): EventoCalificado => $close('2026-10-01', 'Retiro'))
        ->toThrow(ValidationException::class, 'vigencia');

    $close('2026-11-01', 'Retiro');

    expect(fn (): EventoCalificado => $close('2026-10-20', 'Otra vez'))
        ->toThrow(ValidationException::class, 'ya fue cerrado');
});

it('does not let another merchant close an enrollment', function (): void {
    $evento = ($this->register)($this->admin, TipoEventoCalificado::MatriculaColegio, $this->colegio);
    $otro = User::factory()->administradorDeComercio($this->inmobiliaria->id)->create();

    expect(fn (): EventoCalificado => app(CloseEventoCalificado::class)(
        $otro,
        $evento->id,
        CarbonImmutable::today(),
        'Retiro',
    ))->toThrow(AuthorizationException::class);
});
