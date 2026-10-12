<?php

declare(strict_types=1);

use App\Actions\Reglas\CreateRegla;
use App\Actions\Reglas\VersionRegla;
use App\Enums\DefinicionSource;
use App\Enums\TipoRegla;
use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Regla;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\ReglaTestData;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00'));
    $this->admin = User::factory()->administradorDelGrupo()->create();
});

it('rejects a key outside the whitelist and persists nothing', function (): void {
    $definicion = [...ReglaTestData::bonificacion(), 'descuento_pesos' => 5000];

    expect(fn (): Regla => app(CreateRegla::class)($this->admin, 'x', TipoRegla::Bonificacion, $definicion))
        ->toThrow(ValidationException::class);

    expect(Regla::query()->count())->toBe(0)
        ->and(Auditoria::query()->where('modelo', Regla::class)->count())->toBe(0);
});

it('versions 3 into 4 closing 3 today without touching its definition', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);

    $this->travel(1)->days();
    $v2 = app(VersionRegla::class)($this->admin, $v1, ReglaTestData::bonificacion(3));

    $this->travel(1)->days();
    $v3 = app(VersionRegla::class)($this->admin, $v2, ReglaTestData::bonificacion(4));

    $this->travel(5)->days();
    $v4 = app(VersionRegla::class)($this->admin, $v3, ReglaTestData::bonificacion(5));

    $v3->refresh();
    $today = CarbonImmutable::now()->toDateString();

    expect($v4->version)->toBe(4)
        ->and($v4->vigente_desde->toDateString())->toBe($today)
        ->and($v4->vigente_hasta)->toBeNull()
        ->and($v3->vigente_hasta?->toDateString())->toBe($today)
        ->and($v3->vigente_hasta?->equalTo($v4->vigente_desde))->toBeTrue()
        ->and($v3->definicion)->toEqual(ReglaTestData::bonificacion(4))
        ->and(Regla::query()->where('codigo', $v1->codigo)->count())->toBe(4);
});

it('rejects a version whose validity overlaps another one', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);
    $scheduled = app(VersionRegla::class)(
        $this->admin,
        $v1,
        ReglaTestData::bonificacion(3),
        vigenteDesde: CarbonImmutable::now()->addDays(10),
    );

    expect(fn (): Regla => app(VersionRegla::class)(
        $this->admin,
        $scheduled,
        ReglaTestData::bonificacion(4),
        vigenteDesde: CarbonImmutable::now()->addDay(),
    ))->toThrow(ValidationException::class);

    expect(Regla::query()->count())->toBe(2);
});

it('rejects a retroactive validity', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);

    expect(fn (): Regla => app(VersionRegla::class)(
        $this->admin,
        $v1,
        ReglaTestData::bonificacion(3),
        vigenteDesde: CarbonImmutable::now()->subDay(),
    ))->toThrow(ValidationException::class);
});

it('forbids creating or versioning rules to anyone but the group administrator', function (User $user): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);

    expect(fn (): Regla => app(VersionRegla::class)($user, $v1, ReglaTestData::bonificacion(3)))
        ->toThrow(AuthorizationException::class)
        ->and(fn (): Regla => ReglaTestData::createBonificacion($user, 'otra'))
        ->toThrow(AuthorizationException::class)
        ->and(Regla::query()->count())->toBe(1);
})->with([
    'merchant administrator' => fn (): User => User::factory()->administradorDeComercio()->create(),
    'cashier' => fn (): User => User::factory()->cajero()->create(),
    'inactive group administrator' => fn (): User => User::factory()->administradorDelGrupo()->inactivo()->create(),
]);

it('audits every save with the author, the date and the previous and new definition', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);
    $this->travel(1)->minutes();
    $v2 = app(VersionRegla::class)($this->admin, $v1, ReglaTestData::bonificacion(3));

    $creation = Auditoria::query()->where('evento', 'regla_creada')->sole();
    $versioning = Auditoria::query()->where('evento', 'regla_versionada')->sole();

    expect($creation->usuario_id)->toBe($this->admin->id)
        ->and($creation->antes)->toBeNull()
        ->and($creation->despues['definicion'] ?? null)->toEqual(ReglaTestData::bonificacion())
        ->and($versioning->usuario_id)->toBe($this->admin->id)
        ->and($versioning->modelo_id)->toBe($v2->id)
        ->and($versioning->created_at?->toDateTimeString())->toBe('2026-10-12 09:01:00')
        ->and($versioning->antes['definicion'] ?? null)->toEqual(ReglaTestData::bonificacion())
        ->and($versioning->despues['definicion'] ?? null)->toEqual(ReglaTestData::bonificacion(3))
        ->and($versioning->despues['version'] ?? null)->toBe(2);
});

it('rejects saving on top of a version that is no longer the latest', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);
    $otherAdmin = User::factory()->administradorDelGrupo()->create();
    $this->travel(1)->minutes();

    app(VersionRegla::class)($otherAdmin, $v1, ReglaTestData::bonificacion(3));

    expect(fn (): Regla => app(VersionRegla::class)($this->admin, $v1, ReglaTestData::bonificacion(4)))
        ->toThrow(ValidationException::class);

    expect(Regla::query()->count())->toBe(2);
});

it('rejects a repeated rule code', function (): void {
    ReglaTestData::createBonificacion($this->admin, 'Promo-Martes');

    expect(fn (): Regla => ReglaTestData::createBonificacion($this->admin, 'promo-martes'))
        ->toThrow(ValidationException::class);
});

it('requires the separate permission to save from the JSON editor', function (): void {
    expect(fn (): Regla => app(CreateRegla::class)(
        $this->admin,
        'json',
        TipoRegla::Bonificacion,
        ReglaTestData::bonificacion(),
        DefinicionSource::JsonEditor,
    ))->toThrow(AuthorizationException::class);

    $editor = User::factory()->administradorDelGrupo()->jsonReglaEditor()->create();
    $regla = app(CreateRegla::class)(
        $editor,
        'json',
        TipoRegla::Bonificacion,
        ReglaTestData::bonificacion(),
        DefinicionSource::JsonEditor,
    );

    expect($regla->version)->toBe(1);
});

it('rejects an acumulacion rule with a category that does not exist', function (): void {
    Categoria::factory()->create(['slug' => 'restaurante']);
    $definicion = [
        'moneda_por_punto' => 1000,
        'tasas_por_categoria' => [
            ['categoria_slug' => 'restaurante', 'puntos_por_unidad' => 1],
            ['categoria_slug' => 'spa', 'puntos_por_unidad' => 1],
        ],
        'vigencia_puntos_meses' => 12,
    ];

    expect(fn (): Regla => app(CreateRegla::class)($this->admin, 'acumulacion', TipoRegla::Acumulacion, $definicion))
        ->toThrow(ValidationException::class);
});

it('seeds the base rules with the values confirmed by the client', function (): void {
    $this->seed();

    $niveles = Regla::query()->where('codigo', 'niveles')->sole();
    $acumulacion = Regla::query()->where('codigo', 'acumulacion-base')->sole();

    expect($niveles->definicion['avisos_dias_antes'])->toBe([30, 7, 1])
        ->and($niveles->definicion['proteccion_meses'])->toBe(0)
        ->and($acumulacion->definicion['moneda_por_punto'])->toBe(1000)
        ->and($acumulacion->version)->toBe(1);
});
