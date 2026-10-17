<?php

declare(strict_types=1);

use App\Actions\Reglas\SimulateReglas;
use App\Enums\DefinicionSource;
use App\Enums\TipoRegla;
use App\Filament\Pages\SimuladorReglas;
use App\Models\Comercio;
use App\Models\Regla;
use App\Models\User;
use App\Reglas\SimulacionResultado;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\SimuladorTestData;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00'));
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->comercioIds = SimuladorTestData::seed();
});

it('matches the fixture line by line and in total', function (): void {
    $resultado = SimuladorTestData::simulate($this->admin, $this->comercioIds);
    $fixture = SimuladorTestData::fixture();

    foreach ($fixture['purchases'] as $index => $scenario) {
        $linea = $resultado->lineas[$index];

        foreach (['vigente' => 'expected_current', 'borrador' => 'expected_draft'] as $side => $expectedKey) {
            $desglose = $linea->{$side};

            expect([
                'base' => $desglose->base,
                'bonus_rule' => $desglose->bonificacion?->codigo,
                'cap' => $desglose->tope,
                'puntos' => $desglose->puntos,
            ])->toBe($scenario[$expectedKey], "{$scenario['_description']} ({$side})");
        }
    }

    expect($resultado->totalVigente())->toBe($fixture['expected_totals']['current'])
        ->and($resultado->totalBorrador())->toBe($fixture['expected_totals']['draft']);
});

it('labels the rule and version of every line, with "borrador" for the draft', function (): void {
    $linea = SimuladorTestData::simulate($this->admin, $this->comercioIds)->lineas[0];

    expect($linea->vigente->acumulacion?->versionLabel())->toBe('v1')
        ->and($linea->vigente->acumulacion?->id)->toBe(Regla::query()->where('codigo', 'acumulacion-base')->value('id'))
        ->and($linea->borrador->acumulacion?->versionLabel())->toBe('borrador')
        ->and($linea->borrador->bonificacion?->versionLabel())->toBe('v1')
        ->and($linea->diferencia())->toBe(170);
});

it('never inserts, updates or deletes a row', function (): void {
    $writes = [];
    DB::listen(static function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|merge|truncate)\b/i', $query->sql) === 1) {
            $writes[] = $query->sql;
        }
    });
    $reglas = Regla::query()->count();

    SimuladorTestData::simulate($this->admin, $this->comercioIds);

    expect($writes)->toBe([])
        ->and(Regla::query()->count())->toBe($reglas)
        ->and(DB::transactionLevel())->toBe(1);
});

it('shows the schema errors of an invalid draft and computes nothing', function (): void {
    try {
        SimuladorTestData::simulate($this->admin, $this->comercioIds, 'invalid_draft');
        $this->fail('The invalid draft was simulated.');
    } catch (ValidationException $exception) {
        expect(implode(' ', $exception->errors()['definicion']))->toContain('esquema');
    }
});

it('simulates a new bonus next to the rules in force', function (): void {
    $resultado = app(SimulateReglas::class)(
        $this->admin,
        'quintuple-heladeria',
        TipoRegla::Bonificacion,
        [
            'multiplicador' => 5,
            'condiciones' => [['campo' => 'categoria_slug', 'operador' => 'igual', 'valor' => 'heladeria']],
        ],
        SimuladorTestData::purchases($this->comercioIds),
    );

    expect($resultado->lineas[1]->vigente->puntos)->toBe(12)
        ->and($resultado->lineas[1]->borrador->puntos)->toBe(60)
        ->and($resultado->lineas[1]->borrador->bonificacion?->isDraft())->toBeTrue();
});

it('rejects drafts the simulator cannot try', function (string $codigo, TipoRegla $tipo, string $message): void {
    $definicion = $tipo === TipoRegla::Bonificacion
        ? ['multiplicador' => 2, 'condiciones' => [['campo' => 'monto', 'operador' => 'mayor', 'valor' => 1]]]
        : SimuladorTestData::fixture()['draft']['definicion'];

    expect(fn (): SimulacionResultado => app(SimulateReglas::class)(
        $this->admin,
        $codigo,
        $tipo,
        $definicion,
        SimuladorTestData::purchases($this->comercioIds),
    ))->toThrow(ValidationException::class, $message);
})->with([
    'levels' => ['umbrales', TipoRegla::Niveles, 'solo prueba reglas de acumulación y bonificación'],
    'a second accrual rule' => ['acumulacion-nueva', TipoRegla::Acumulacion, 'Ya rige la regla de acumulación'],
    'a code of another type' => ['acumulacion-base', TipoRegla::Bonificacion, 'es de tipo Acumulación'],
]);

it('lets only the group administrator simulate', function (): void {
    $user = User::factory()->administradorDeComercio(Comercio::query()->value('id'))->create();

    expect(fn (): SimulacionResultado => SimuladorTestData::simulate($user, $this->comercioIds))
        ->toThrow(AuthorizationException::class);
});

it('requires the JSON editor permission for a draft written in JSON', function (): void {
    $draft = SimuladorTestData::fixture()['draft'];

    expect(fn (): SimulacionResultado => app(SimulateReglas::class)(
        $this->admin,
        $draft['codigo'],
        TipoRegla::Acumulacion,
        $draft['definicion'],
        SimuladorTestData::purchases($this->comercioIds),
        DefinicionSource::JsonEditor,
    ))->toThrow(AuthorizationException::class);
});

it('opens the page for the group administrator and returns 403 to anyone else', function (): void {
    $this->actingAs($this->admin)->get('/admin/simulador-reglas')->assertSuccessful()->assertSee('Simulador de reglas');

    $this->actingAs(User::factory()->administradorDeComercio(Comercio::query()->value('id'))->create())
        ->get('/admin/simulador-reglas')
        ->assertForbidden();
});

it('fills the draft from the chosen rule and shows the comparison', function (): void {
    $regla = Regla::query()->where('codigo', 'acumulacion-base')->sole();
    $this->actingAs($this->admin);

    Livewire::test(SimuladorReglas::class)
        ->fillForm(['regla_id' => $regla->id])
        ->assertSchemaStateSet(['codigo' => 'acumulacion-base', 'tipo' => 'acumulacion'])
        ->fillForm([
            'definicion.tasas_por_categoria' => [['categoria_slug' => 'restaurante', 'puntos_por_unidad' => '2']],
            'comercio_id' => $this->comercioIds[1],
            'compras' => [['monto' => '85000', 'fecha' => '2026-10-13 10:00', 'sede_id' => null]],
        ])
        ->call('simulate')
        ->assertHasNoFormErrors()
        ->assertSet('errores', [])
        ->assertSet('resultado.total_vigente', 170)
        ->assertSet('resultado.total_borrador', 340)
        ->assertSee('borrador');
});

it('shows the validation errors of the draft on the page', function (): void {
    $regla = Regla::query()->where('codigo', 'acumulacion-base')->sole();
    $this->actingAs(User::factory()->administradorDelGrupo()->create(['edita_reglas_json' => true]));

    Livewire::test(SimuladorReglas::class)
        ->fillForm(['regla_id' => $regla->id])
        ->fillForm([
            'use_json_editor' => true,
            'definicion_json' => json_encode(SimuladorTestData::fixture()['invalid_draft']['definicion']),
            'comercio_id' => $this->comercioIds[1],
            'compras' => [['monto' => '85000', 'fecha' => '2026-10-13 10:00', 'sede_id' => null]],
        ])
        ->call('simulate')
        ->assertSet('resultado', null)
        ->assertSee('El borrador no se puede simular')
        ->assertSee('esquema');
});
