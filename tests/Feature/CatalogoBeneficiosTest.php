<?php

declare(strict_types=1);

use App\Beneficios\CatalogoCliente;
use App\Beneficios\FilaCatalogo;
use App\Livewire\Cliente\Beneficios;
use App\Models\Beneficio;
use App\Models\Comercio;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CatalogoTestData;

// Catálogo de beneficios de la PWA (E8-04). Los beneficios, el día y los textos salen de
// tests/Fixtures/catalogo-pwa-escenarios.json.

beforeEach(function (): void {
    $this->travelTo(CatalogoTestData::hoy());
    $this->datos = CatalogoTestData::seed();
    $this->fixture = CatalogoTestData::fixture();
    $this->actingAs($this->datos->cliente, 'cliente');
    $this->secciones = fn (array $filas): array => array_map(
        fn (FilaCatalogo $fila): array => ['id' => $fila->beneficioId(), 'meta' => $fila->meta],
        $filas,
    );
    $this->esperadas = fn (array $filas): array => array_map(
        fn (array $fila): array => ['id' => $this->datos->id($fila['key']), 'meta' => $fila['meta']],
        $filas,
    );
});

it('builds the three sections, their order and texts from the fixture', function (): void {
    $catalogo = app(CatalogoCliente::class)->para($this->datos->cliente);
    $esperado = $this->fixture['expected_catalog']['sections'];

    expect(($this->secciones)($catalogo->disponibles))->toBe(($this->esperadas)($esperado['Puedes usarlos']))
        ->and(($this->secciones)($catalogo->porAhoraNo))->toBe(($this->esperadas)($esperado['Por ahora no']))
        ->and(($this->secciones)($catalogo->bloqueados))
        ->toBe(($this->esperadas)($esperado['Se abren al subir de nivel']))
        ->and(array_values($catalogo->fichas))->toBe(array_slice($this->fixture['expected_catalog']['chips'], 1));
});

it('shows the catalog with its sections, chips and locked pills', function (): void {
    $catalogo = $this->fixture['expected_catalog'];
    $pantalla = Livewire::test(Beneficios::class)
        ->assertSee($catalogo['available_count_label'])
        ->assertSeeInOrder(['Puedes usarlos', 'Por ahora no', 'Se abren al subir de nivel'])
        ->assertSeeInOrder($catalogo['chips']);

    foreach ($catalogo['sections']['Se abren al subir de nivel'] as $fila) {
        $pantalla->assertSee($fila['meta'])->assertSee(ucfirst($fila['nivel']));
    }

    // Los bloqueados no se atenúan (club-ar-ui, regla 7).
    expect($pantalla->html())->not->toContain('opacity');
});

it('filters the sections by the chosen category and hides an empty section', function (): void {
    $filtro = $this->fixture['category_filter'];
    $catalogo = app(CatalogoCliente::class)->para($this->datos->cliente, $filtro['categoria']);

    expect(array_map(fn (FilaCatalogo $f): int => $f->beneficioId(), $catalogo->disponibles))
        ->toBe(array_map($this->datos->id(...), $filtro['sections']['Puedes usarlos']))
        ->and(array_map(fn (FilaCatalogo $f): int => $f->beneficioId(), $catalogo->porAhoraNo))
        ->toBe(array_map($this->datos->id(...), $filtro['sections']['Por ahora no']))
        ->and($catalogo->bloqueados)->toBe([]);

    Livewire::withQueryParams(['categoria' => $filtro['categoria']])
        ->test(Beneficios::class)
        ->assertDontSee($filtro['hidden_sections'][0]);
});

it('falls back to all the categories with an unknown one', function (): void {
    expect(app(CatalogoCliente::class)->para($this->datos->cliente, 'no-existe')->categoria)->toBeNull();
});

it('asks for the birth date when the customer has none', function (): void {
    $caso = $this->fixture['without_birthdate'];
    $this->datos->cliente->forceFill(['fecha_nacimiento' => null])->save();
    $catalogo = app(CatalogoCliente::class)->para($this->datos->cliente->refresh());
    $fila = collect($catalogo->porAhoraNo)
        ->first(fn (FilaCatalogo $f): bool => $f->beneficioId() === $this->datos->id($caso['key']));

    expect($fila?->meta)->toBe($caso['meta']);
});

it('reads the catalog with a fixed number of queries', function (): void {
    $contar = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(CatalogoCliente::class)->para($this->datos->cliente);

        return count(DB::getQueryLog());
    };
    $conPocos = $contar();

    foreach (range(1, 9) as $n) {
        Beneficio::query()->forceCreate([
            ...$this->datos->beneficios['martes_bbc']->only(['tipo', 'condiciones', 'vigente_desde', 'activo']),
            'comercio_id' => Comercio::factory()->create()->id,
            'nombre' => "Extra {$n}",
            'valores_por_nivel' => ['plata' => 10],
        ]);
    }

    expect($contar())->toBe($conPocos);
});

it('never says canjear, costo or saldo', function (): void {
    $html = mb_strtolower(Livewire::test(Beneficios::class)->html());

    foreach ($this->fixture['forbidden_words'] as $palabra) {
        expect($html)->not->toContain($palabra);
    }
});

it('links the tab and "Ver los N" of the home to the catalog', function (): void {
    $this->get(route('cliente.niveles'))->assertSee(route('cliente.beneficios'));
    $this->get(route('cliente.beneficios'))->assertOk();
});

it('sends a guest to the sign-in screen', function (): void {
    auth('cliente')->logout();

    $this->get('/beneficios')->assertRedirect(route('cliente.ingreso'));
});
