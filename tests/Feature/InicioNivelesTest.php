<?php

declare(strict_types=1);

use App\Actions\Reglas\VersionRegla;
use App\Enums\Nivel;
use App\Livewire\Cliente\Inicio;
use App\Livewire\Cliente\Niveles;
use App\Livewire\Cliente\ParaUsarHoy;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Comercio;
use App\Models\User;
use App\Niveles\PantallaNiveles;
use App\Niveles\ResumenNivel;
use App\Support\FormatoColombiano;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\BeneficiosTestData;
use Tests\Support\InicioTestData;
use Tests\Support\MembresiasTestData;
use Tests\Support\NivelesTestData;

// Inicio y niveles del cliente (E8-03). Los clientes, el tope de "Para usar hoy" y las tarjetas
// salen de tests/Fixtures/inicio-niveles-escenarios.json; los umbrales, de la regla `niveles`.

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00:00'));
    $this->regla = NivelesTestData::nivelesRegla();
    $categoria = Categoria::query()->firstOrCreate(['slug' => 'varios'], ['nombre' => 'Varios']);
    $this->heladeria = Comercio::factory()->create([
        'razon_social' => 'Heladería Polo',
        'categoria_id' => $categoria->id,
    ]);
    $this->cerveceria = Comercio::factory()->create([
        'razon_social' => 'Cervecería Andina',
        'categoria_id' => $categoria->id,
    ]);
});

it('keeps the today list limit equal to the fixture', function (): void {
    $tope = InicioTestData::inicioFixture()['home']['today_list']['max_items']['value'];

    expect(config('inicio.para_usar_hoy_max'))->toBe($tope);
});

it('shows each customer their level, what is missing for the next one and the progress', function (array $caso): void {
    $cliente = InicioTestData::clienteDelFixture($caso);
    $esperado = $caso['expected'];
    $resumen = app(ResumenNivel::class)->para($cliente);

    expect($resumen->nivel->value)->toBe($esperado['nivel_efectivo'])
        ->and($resumen->siguiente?->value)->toBe($esperado['next_level'])
        ->and($resumen->faltan)->toBe($esperado['missing_points'])
        ->and($resumen->porcentaje)->toBe($esperado['progress_percent'])
        ->and($resumen->origen->value)->toBe($esperado['origin'])
        ->and($resumen->hasta?->toDateString())->toBe(
            isset($esperado['origin_until']) ? NivelesTestData::day($esperado['origin_until'])->toDateString() : null,
        );

    $this->actingAs($cliente, 'cliente');
    $pantalla = Livewire::test(Inicio::class)
        ->assertSee("var(--color-tier-{$esperado['nivel_efectivo']}-bg)", false)
        ->assertSee($esperado['greeting'])
        ->assertSee(Nivel::from($esperado['nivel_efectivo'])->getLabel());

    if ($esperado['next_level'] === null) {
        $pantalla->assertSee('Estás en el nivel más alto del Club.')
            ->assertDontSee('puntos más para')
            ->assertDontSee('role="progressbar"', false);
    } else {
        $pantalla->assertSeeInOrder([
            number_format($esperado['missing_points'], 0, ',', '.'),
            'puntos más para',
            Nivel::from($esperado['next_level'])->getLabel(),
        ])->assertSee('aria-valuenow="' . $esperado['progress_percent'] . '"', false);
    }

    if (isset($esperado['origin_until'])) {
        $origen = $esperado['origin'] === 'membresia' ? 'Por tu suscripción' : 'Por tu compra';
        $hasta = NivelesTestData::day($esperado['origin_until']);
        $fecha = $hasta->year === now()->year
            ? FormatoColombiano::fecha($hasta)
            : FormatoColombiano::fechaConAnio($hasta);
        $pantalla->assertSee("{$origen}, hasta el {$fecha}");
    } else {
        $pantalla->assertDontSee('Por tu suscripción')->assertDontSee('Por tu compra');
    }
})->with(InicioTestData::clientesDelFixture());

it('shows the origin that lasts longer when the membership and an event give the same level', function (): void {
    $cliente = NivelesTestData::cliente([
        'saldo_puntos' => 600,
        'nivel_por_puntos' => 'plata',
        'nivel_por_membresia' => 'diamante',
        'membresia_vigente_hasta' => 'hoy+1m',
        'nivel_por_evento' => 'diamante',
        'evento_vigente_hasta' => 'hoy+6m',
    ]);

    $resumen = app(ResumenNivel::class)->para($cliente);

    expect($resumen->origen->value)->toBe('evento')
        ->and($resumen->hasta?->toDateString())->toBe(NivelesTestData::day('hoy+6m')->toDateString());
});

it('renders the level on the first response and loads the today list afterwards', function (): void {
    $this->actingAs(NivelesTestData::cliente(['saldo_puntos' => 1160, 'nivel_por_puntos' => 'plata']), 'cliente');

    $this->get(route('cliente.inicio'))
        ->assertOk()
        ->assertSee('Plata')
        ->assertSee('puntos más para')
        ->assertSee('Cargando tus beneficios');
});

it('lists only the available benefits up to the limit, ordered by merchant and name', function (): void {
    $max = InicioTestData::inicioFixture()['home']['today_list']['max_items']['value'];
    $cliente = NivelesTestData::cliente(['saldo_puntos' => 600, 'nivel_por_puntos' => 'plata']);
    $nombres = [];

    foreach (range(1, $max + 1) as $numero) {
        $nombres[] = sprintf('Helado %02d', $numero);
        InicioTestData::beneficioDeInicio($this->heladeria, end($nombres), ['plata' => 10]);
    }

    InicioTestData::beneficioDeInicio($this->cerveceria, 'Cerveza de la casa', ['plata' => 15]);
    InicioTestData::beneficioDeInicio($this->cerveceria, 'Solo para Oro', ['oro' => 20]);
    $usado = InicioTestData::beneficioDeInicio($this->cerveceria, 'Usado este mes', ['plata' => 10], [
        'max_por_cliente_ventana' => 'mes',
        'max_por_cliente_cantidad' => 1,
    ]);
    $cajero = User::factory()->cajero($this->cerveceria->id)->create();
    BeneficiosTestData::redencion($usado, $cliente, '2026-11-02', $cajero);

    $this->actingAs($cliente, 'cliente');

    // Cervecería va antes que Heladería: entran la cerveza y los primeros helados, el último queda fuera.
    Livewire::withoutLazyLoading()->test(ParaUsarHoy::class)
        ->assertSeeInOrder(['Cerveza de la casa', ...array_slice($nombres, 0, $max - 1)])
        ->assertDontSee($nombres[$max - 1])
        ->assertDontSee(end($nombres))
        ->assertDontSee('Solo para Oro')
        ->assertDontSee('Usado este mes')
        ->assertSee('Ver los ' . ($max + 2));
});

it('shows the empty state with a link to the levels screen when nothing is available today', function (): void {
    InicioTestData::beneficioDeInicio($this->cerveceria, 'Solo para Diamante', ['diamante' => 20]);
    $this->actingAs(NivelesTestData::cliente(['saldo_puntos' => 100]), 'cliente');

    Livewire::withoutLazyLoading()->test(ParaUsarHoy::class)
        ->assertSee('Hoy no tienes beneficios para usar')
        ->assertSee('Ver cómo subir de nivel')
        ->assertSee(route('cliente.niveles'))
        ->assertDontSee('Solo para Diamante')
        ->assertDontSee('Ver los');
});

it('reads the today list with a fixed number of queries', function (): void {
    $cliente = NivelesTestData::cliente(['saldo_puntos' => 600, 'nivel_por_puntos' => 'plata']);
    $this->actingAs($cliente, 'cliente');
    $contar = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::withoutLazyLoading()->test(ParaUsarHoy::class);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    InicioTestData::beneficioDeInicio($this->heladeria, 'Uno', ['plata' => 10], [
        'max_por_cliente_ventana' => 'mes',
        'max_por_cliente_cantidad' => 1,
    ]);
    $conUno = $contar();

    foreach (range(1, 6) as $numero) {
        $comercio = $numero % 2 === 0 ? $this->heladeria : $this->cerveceria;
        InicioTestData::beneficioDeInicio($comercio, "Otro {$numero}", ['plata' => 10], ['cupo_global' => 50]);
    }

    expect($contar())->toBeLessThanOrEqual($conUno + 1);
});

it('shows the four level cards of the fixture to the Plata customer', function (): void {
    $fixture = InicioTestData::inicioFixture();
    $caso = collect($fixture['customers'])->firstWhere('nombre_completo', $fixture['levels_screen']['customer']);
    $cliente = InicioTestData::clienteDelFixture($caso);

    $tarjetas = app(PantallaNiveles::class)->tarjetas($cliente);
    $leidas = array_map(static fn ($tarjeta): array => [
        'nivel' => $tarjeta->nivel->value,
        'threshold_text' => $tarjeta->umbral === null ? 'Al registrarte' : FormatoColombiano::puntos($tarjeta->umbral),
        'current' => $tarjeta->actual,
        'missing_points' => $tarjeta->faltan,
    ], $tarjetas);

    expect($leidas)->toBe($fixture['levels_screen']['expected_cards']);

    $this->actingAs($cliente, 'cliente');
    Livewire::test(Niveles::class)
        ->assertSee('Los beneficios no gastan puntos.')
        ->assertSeeInOrder(['Bronce', 'Al registrarte', 'Plata', 'TU NIVEL', 'Oro', 'Te faltan 340 pts', 'Diamante'])
        ->assertSee('Tres formas de llegar: acumular ' . FormatoColombiano::puntos(3000))
        ->assertDontSee('canje');
});

it('counts the valid benefits each level receives', function (): void {
    InicioTestData::beneficioDeInicio($this->heladeria, 'Para todos', [
        'bronce' => 5,
        'plata' => 10,
        'oro' => 15,
        'diamante' => 20,
    ]);
    InicioTestData::beneficioDeInicio($this->cerveceria, 'Desde Oro', ['oro' => 15, 'diamante' => 20]);
    InicioTestData::beneficioDeInicio($this->cerveceria, 'Inactivo', ['bronce' => 5], ['activo' => false]);
    InicioTestData::beneficioDeInicio($this->cerveceria, 'Vencido', ['bronce' => 5], ['vigente_hasta' => '2026-10-31']);
    $cliente = NivelesTestData::cliente([]);

    $tarjetas = app(PantallaNiveles::class)->tarjetas($cliente);
    $conteo = array_map(static fn ($tarjeta): int => $tarjeta->beneficios, $tarjetas);

    expect($conteo)->toBe([1, 1, 2, 2]);

    $this->actingAs($cliente, 'cliente');
    Livewire::test(Niveles::class)->assertSee('Al registrarte · 1 beneficio')->assertSee('2 beneficios');
});

it('explains the Diamante ways with the configured numbers and links to the membership', function (): void {
    $plan = MembresiasTestData::plan();
    MembresiasTestData::plan(12);
    $meses = config('niveles.event_rules.compra_inmueble.default_validity_months');
    $this->actingAs(NivelesTestData::cliente([]), 'cliente');

    $duracion = $meses === 12 ? 'un año' : "{$meses} meses";

    Livewire::test(Niveles::class)
        ->assertSee("o comprar un inmueble con la inmobiliaria del grupo ({$duracion} de Diamante)")
        ->assertSee('Suscripción desde ' . FormatoColombiano::pesos($plan->precio_cop) . '/mes')
        ->assertSee(route('cliente.membresia'));
});

it('labels the button "Ver mi suscripción" when the customer has a valid membership', function (): void {
    MembresiasTestData::plan();
    $this->actingAs(NivelesTestData::cliente([
        'nivel_por_membresia' => 'diamante',
        'membresia_vigente_hasta' => 'hoy+20d',
    ]), 'cliente');

    Livewire::test(Niveles::class)
        ->assertSee('Ver mi suscripción')
        ->assertDontSee('Suscripción desde')
        // Diamante es invertido: "TU NIVEL" va en su fondo oscuro, no en blanco sobre la tinta clara.
        ->assertSeeInOrder([
            'style="background: var(--color-tier-diamante-ink); color: var(--color-tier-diamante-bg)"',
            'TU NIVEL',
        ], false);
});

it('follows a new version of the niveles rule without touching any view', function (): void {
    $cliente = NivelesTestData::cliente(['saldo_puntos' => 1160, 'nivel_por_puntos' => 'plata']);
    $definicion = $this->regla->definicion;
    $definicion['umbrales'] = array_map(
        static fn (array $umbral): array => $umbral['nivel'] === 'oro'
            ? [...$umbral, 'umbral_puntos' => 2000]
            : $umbral,
        $definicion['umbrales'],
    );
    app(VersionRegla::class)(User::factory()->administradorDelGrupo()->create(), $this->regla, $definicion);
    $this->actingAs($cliente, 'cliente');

    Livewire::test(Inicio::class)->assertSeeInOrder(['840', 'puntos más para', 'Oro']);
    Livewire::test(Niveles::class)->assertSee('2.000 pts')->assertSee('Te faltan 840 pts');
});

it('sends a guest to the sign-in screen', function (string $ruta): void {
    $this->get(route($ruta))->assertRedirect(route('cliente.ingreso'));
})->with(['cliente.inicio', 'cliente.niveles']);
