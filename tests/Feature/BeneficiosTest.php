<?php

declare(strict_types=1);

use App\Actions\Beneficios\SaveBeneficio;
use App\Beneficios\BeneficioParaCliente;
use App\Beneficios\BeneficiosParaCliente;
use App\Beneficios\CondicionesBeneficio;
use App\Enums\VentanaBeneficio;
use App\Filament\Resources\Beneficios\BeneficioResource;
use App\Filament\Resources\Beneficios\Pages\CreateBeneficio;
use App\Filament\Resources\Beneficios\Pages\EditBeneficio;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\BeneficiosTestData;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00:00'));
    $this->admin = BeneficiosTestData::seed();
    // El estado de un beneficio para ese cliente en ese día.
    $this->estado = function (Cliente $cliente, string $nombre, string $hoy): ?BeneficioParaCliente {
        $this->travelTo(CarbonImmutable::parse("{$hoy} 15:00:00"));

        foreach (app(BeneficiosParaCliente::class)->para($cliente) as $item) {
            if ($item->beneficio->nombre === $nombre) {
                return $item;
            }
        }

        return null;
    };
    // Compara un estado con lo que espera el fixture.
    $this->expectEstado = function (?BeneficioParaCliente $item, array $expected): void {
        expect($item)->not->toBeNull()
            ->and($item?->estado->value)->toBe($expected['estado']);

        if (array_key_exists('valor', $expected)) {
            expect($item?->valor)->toBe($expected['valor']);
        }

        if (array_key_exists('vuelve', $expected)) {
            expect($item?->vuelve?->toDateString())->toBe($expected['vuelve']);
        }

        if (array_key_exists('proxima_fecha', $expected)) {
            expect($item?->proximaFecha?->toDateString())->toBe($expected['proxima_fecha']);
        }

        if (array_key_exists('motivo', $expected)) {
            expect($item?->motivo)->toBe($expected['motivo']);
        }

        if (array_key_exists('nivel_requerido', $expected)) {
            expect($item?->nivelRequerido?->value)->toBe($expected['nivel_requerido'])
                ->and($item?->puntosFaltantes)->toBe($expected['puntos_faltantes']);
        }

        if (array_key_exists('siguiente_nivel', $expected)) {
            $siguiente = $item?->siguiente;

            expect([
                $siguiente['nivel']->value ?? null,
                $siguiente['valor'] ?? null,
                $siguiente['puntos_faltantes'] ?? null,
            ])->toBe(array_values($expected['siguiente_nivel']));
        }

        if (array_key_exists('requiere_soporte', $expected)) {
            expect($item?->beneficio->requiere_soporte)->toBe($expected['requiere_soporte']);
        }
    };
});

it('keeps the code settings equal to the fixture', function (): void {
    $settings = BeneficiosTestData::escenarios()['settings'];
    $schema = json_decode((string) file_get_contents(resource_path('schemas/beneficio-condiciones.json')), true);

    expect(CondicionesBeneficio::FIN_DE_SEMANA)->toBe($settings['weekend_days']['value'])
        ->and(array_map(static fn (VentanaBeneficio $ventana): string => $ventana->value, VentanaBeneficio::cases()))
        ->toBe($settings['windows'])
        ->and(array_keys($schema['properties']))->toBe($settings['allowed_conditions']);
});

it('seeds the initial catalog from the fixture', function (): void {
    $catalogo = BeneficiosTestData::catalogo()['beneficios'];

    foreach ($catalogo as $esperado) {
        $beneficio = BeneficiosTestData::beneficio($esperado['nombre']);

        expect($beneficio->comercio_id)->toBe(BeneficiosTestData::comercioId($esperado['comercio_nit']))
            ->and($beneficio->tipo->value)->toBe($esperado['tipo'])
            ->and($beneficio->valores_por_nivel)->toEqual($esperado['valores_por_nivel'])
            ->and($beneficio->condiciones)->toEqual($esperado['condiciones'])
            ->and($beneficio->max_por_cliente_ventana?->value)->toBe($esperado['max_por_cliente']['ventana'] ?? null)
            ->and($beneficio->max_por_cliente_cantidad)->toBe($esperado['max_por_cliente']['cantidad'] ?? null)
            ->and($beneficio->cupo_global)->toBe($esperado['cupo_global'])
            ->and($beneficio->requiere_soporte)->toBe($esperado['requiere_soporte']);
    }

    $extra = BeneficiosTestData::escenarios()['extra_benefits'];

    expect(Beneficio::query()->count())->toBe(count($catalogo) + count($extra));
});

it('rejects conditions outside the whitelist or with a free expression', function (array $condiciones): void {
    $guardar = fn (): Beneficio => app(SaveBeneficio::class)($this->admin, [
        'comercio_id' => BeneficiosTestData::comercioId('800100100'),
        'nombre' => 'Prueba',
        'tipo' => 'porcentaje',
        'valores_por_nivel' => ['bronce' => 10, 'plata' => 10, 'oro' => 10, 'diamante' => 10],
        'condiciones' => $condiciones,
        'vigente_desde' => '2026-10-01',
    ]);

    expect($guardar)->toThrow(ValidationException::class);
    expect(Beneficio::query()->where('nombre', 'Prueba')->exists())->toBeFalse();
})->with(array_map(
    static fn (array $invalida): array => [$invalida['condiciones']],
    BeneficiosTestData::escenarios()['invalid_conditions'],
));

it('rejects values out of range or of an unknown level', function (string $tipo, array $valores): void {
    $guardar = fn (): Beneficio => app(SaveBeneficio::class)($this->admin, [
        'comercio_id' => BeneficiosTestData::comercioId('800100100'),
        'nombre' => 'Prueba',
        'tipo' => $tipo,
        'valores_por_nivel' => $valores,
        'condiciones' => [],
        'vigente_desde' => '2026-10-01',
    ]);

    expect($guardar)->toThrow(ValidationException::class);
})->with([
    'percentage over 100' => ['porcentaje', ['bronce' => 10, 'plata' => 20, 'oro' => 40, 'diamante' => 120]],
    'zero pesos' => ['monto_fijo', ['diamante' => 0]],
    'unknown level' => ['porcentaje', ['platino' => 10]],
    'no level' => ['porcentaje', []],
]);

it('gives the state of each fixture case', function (string $criterion): void {
    $case = BeneficiosTestData::fixtureCase($criterion);
    $steps = $case['steps'] ?? $case['clientes'] ?? [$case];
    $otro = BeneficiosTestData::cliente(['nivel_efectivo' => 'bronce', 'saldo_puntos' => 0]);

    foreach ($steps as $step) {
        DB::table('redenciones')->delete();
        $cliente = BeneficiosTestData::cliente($step['cliente'] ?? $case['cliente']);
        $beneficio = BeneficiosTestData::beneficio($case['beneficio']);

        foreach ($step['redenciones_previas'] ?? [] as $previa) {
            BeneficiosTestData::redencion($beneficio, $cliente, $previa['fecha'], $this->admin);
        }

        foreach (range(1, $step['redenciones_de_otros'] ?? 0) as $i) {
            if (($step['redenciones_de_otros'] ?? 0) > 0) {
                BeneficiosTestData::redencion($beneficio, $otro, '2026-09-1' . ($i % 10), $this->admin);
            }
        }

        ($this->expectEstado)(
            ($this->estado)($cliente, $case['beneficio'], $step['today'] ?? $case['today']),
            $step['expected'],
        );
    }
})->with([
    'a weekday benefit is available on its day and suggests the next level',
    'a weekday benefit is not available on other days',
    'the birthday benefit only on the birthday',
    'the birthday benefit needs the birth date',
    'a February 29 birthday falls on February 28',
    'lifetime limit and global quota',
    'a weekend benefit with a monthly limit',
    'a higher-level benefit is locked with the missing points',
    'a benefit can require a support',
]);

it('never lists an expired or inactive benefit', function (): void {
    $case = BeneficiosTestData::fixtureCase('expired or inactive benefits are not listed');
    $cliente = BeneficiosTestData::cliente($case['cliente']);

    foreach ($case['deactivate_before'] as $nombre) {
        $beneficio = BeneficiosTestData::beneficio($nombre);
        app(SaveBeneficio::class)($this->admin, [...$beneficio->attributesToArray(), 'activo' => false], $beneficio);
    }

    $this->travelTo(CarbonImmutable::parse("{$case['today']} 15:00:00"));
    $nombres = array_map(
        static fn (BeneficioParaCliente $item): string => $item->beneficio->nombre,
        app(BeneficiosParaCliente::class)->para($cliente),
    );

    expect(array_intersect($case['expected']['not_listed'], $nombres))->toBe([])
        ->and($nombres)->not->toBeEmpty();
});

it('never lists the benefits of an inactive merchant', function (): void {
    $beneficio = BeneficiosTestData::beneficio('Martes de cerveza de línea básica en Cervecería BBC');
    $cliente = BeneficiosTestData::cliente(['nivel_efectivo' => 'bronce', 'saldo_puntos' => 0]);
    $nombres = static fn (): array => array_map(
        static fn (BeneficioParaCliente $item): string => $item->beneficio->nombre,
        app(BeneficiosParaCliente::class)->para($cliente),
    );

    expect($nombres())->toContain($beneficio->nombre);

    $beneficio->comercio()->sole()->forceFill(['activo' => false])->save();

    expect($nombres())->not->toContain($beneficio->nombre)->not->toBeEmpty();
});

it('runs the same number of queries no matter how many benefits there are', function (): void {
    $cliente = BeneficiosTestData::cliente(['nivel_efectivo' => 'oro', 'saldo_puntos' => 2000]);
    $contar = function () use ($cliente): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(BeneficiosParaCliente::class)->para($cliente, CarbonImmutable::parse('2026-10-27'));
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $antes = $contar();
    $base = BeneficiosTestData::beneficio('Kilo de carne en El Rancho de Javi');

    foreach (range(1, 10) as $i) {
        $copia = app(SaveBeneficio::class)($this->admin, [...$base->attributesToArray(), 'nombre' => "Copia {$i}"]);
        BeneficiosTestData::redencion($copia, $cliente, '2026-10-02', $this->admin);
    }

    expect($contar())->toBe($antes)
        ->and($antes)->toBeLessThanOrEqual(5);
});

it('lets a merchant administrator see and save only the benefits of their merchant', function (): void {
    $rancho = BeneficiosTestData::comercioId('800100100');
    $admin = User::factory()->administradorDeComercio($rancho)->create();
    $ajeno = BeneficiosTestData::beneficio('Martes de cerveza de línea básica en Cervecería BBC');

    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    expect(BeneficioResource::getEloquentQuery()->pluck('comercio_id')->unique()->values()->all())->toBe([$rancho])
        ->and(fn (): Beneficio => app(SaveBeneficio::class)($admin, $ajeno->attributesToArray(), $ajeno))
        ->toThrow(AuthorizationException::class)
        ->and(fn (): Beneficio => app(SaveBeneficio::class)($admin, [
            ...$ajeno->attributesToArray(),
            'nombre' => 'Nuevo en BBC',
        ]))->toThrow(AuthorizationException::class);

    $cajero = User::factory()->cajero($rancho)->create();
    $this->actingAs($cajero);

    expect(BeneficioResource::canAccess())->toBeFalse();
});

it('never deletes a benefit with redemptions', function (): void {
    $cliente = BeneficiosTestData::cliente(['nivel_efectivo' => 'bronce', 'saldo_puntos' => 0]);
    $usado = BeneficiosTestData::beneficio('Kilo de carne en El Rancho de Javi');
    $libre = BeneficiosTestData::beneficio('Postre de la casa (solo Oro y Diamante)');
    BeneficiosTestData::redencion($usado, $cliente, '2026-09-20', $this->admin);

    expect($this->admin->can('delete', $usado))->toBeFalse()
        ->and($this->admin->can('delete', $libre))->toBeTrue()
        ->and(fn () => $usado->delete())->toThrow(LogicException::class);
});

it('creates and edits a benefit through the panel', function (): void {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(CreateBeneficio::class)
        ->fillForm([
            'comercio_id' => BeneficiosTestData::comercioId('800100100'),
            'nombre' => 'Postre los domingos',
            'tipo' => 'monto_fijo',
            'valores_por_nivel' => ['oro' => '12000', 'diamante' => '15000'],
            'condiciones' => ['dia_semana' => ['domingo'], 'es_cumpleanos' => false],
            'max_por_cliente_ventana' => 'mes',
            'max_por_cliente_cantidad' => '1',
            'vigente_desde' => '2026-10-01',
            'activo' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $beneficio = BeneficiosTestData::beneficio('Postre los domingos');

    expect($beneficio->valores_por_nivel)->toEqual(['oro' => 12000, 'diamante' => 15000])
        ->and($beneficio->condiciones)->toBe(['dia_semana' => ['domingo']])
        ->and($beneficio->nivelMinimo()->value)->toBe('oro');

    Livewire::test(EditBeneficio::class, ['record' => $beneficio->getRouteKey()])
        ->fillForm(['valores_por_nivel' => ['oro' => '14000', 'diamante' => '15000']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($beneficio->refresh()->valores_por_nivel)->toEqual(['oro' => 14000, 'diamante' => 15000]);

    $this->get(BeneficioResource::getUrl('index'))->assertSuccessful()->assertSee('Postre los domingos');
});
