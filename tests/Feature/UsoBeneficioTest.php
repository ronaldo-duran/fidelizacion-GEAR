<?php

declare(strict_types=1);

use App\Livewire\Cliente\Beneficios;
use App\Livewire\Cliente\UsarBeneficio;
use App\Models\Beneficio;
use App\Models\Comercio;
use App\Models\CredencialQr;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CatalogoTestData;

// Uso del beneficio en la PWA (E8-04, reglas 6 a 11): el detalle de cada estado, el código que se
// renueva y "Otros para hoy". Los casos salen de tests/Fixtures/catalogo-pwa-escenarios.json; los
// tiempos del código, de qr-escenarios.json.

beforeEach(function (): void {
    $this->travelTo(CatalogoTestData::hoy());
    $this->datos = CatalogoTestData::seed();
    $this->fixture = CatalogoTestData::fixture();
    $this->actingAs($this->datos->cliente, 'cliente');
});
it('keeps the others-for-today limit equal to the fixture', function (): void {
    expect(config('catalogo.otros_para_hoy_max'))->toBe($this->fixture['detail']['others_today']['max_items']['value']);
});

it('shows each detail case of the fixture and issues a code only when available', function (array $caso): void {
    $pantalla = Livewire::test(UsarBeneficio::class, ['beneficio' => (string) $this->datos->id($caso['key'])])
        ->call('renovar');

    foreach ($caso['expected_texts'] as $texto) {
        $pantalla->assertSee($texto);
    }

    foreach ($caso['others_today'] ?? [] as $otro) {
        $pantalla->assertSee($this->datos->beneficios[$otro]->nombre);
    }

    $credenciales = CredencialQr::query()->where('beneficio_id', $this->datos->id($caso['key']));

    expect($credenciales->count())->toBe($caso['shows_code'] ? 1 : 0)
        ->and($pantalla->get('codigo') !== null)->toBe($caso['shows_code']);

    if ($caso['shows_code']) {
        $credencial = $credenciales->sole();

        expect($credencial->comercio_id)->toBe($this->datos->beneficios[$caso['key']]->comercio_id)
            ->and($pantalla->get('codigo'))->toBe($credencial->codigo_corto)
            ->and($pantalla->get('imagenQr'))->toContain('<svg');
    }
})->with(fn (): array => collect(CatalogoTestData::fixture()['detail_cases'])
    ->mapWithKeys(fn (array $caso): array => [$caso['_description'] => [$caso]])
    ->all());

it('issues a new code on each renewal and lets the old one expire on its own', function (): void {
    $id = (string) $this->datos->id('martes_bbc');
    $pantalla = Livewire::test(UsarBeneficio::class, ['beneficio' => $id])->call('renovar');
    $primero = $pantalla->get('codigo');

    $this->travel(config('credenciales.refresh_seconds'))->seconds();
    $pantalla->call('renovar');

    $viejo = CredencialQr::query()->where('codigo_corto', $primero)->sole();

    expect($pantalla->get('codigo'))->not->toBe($primero)
        ->and(CredencialQr::query()->where('beneficio_id', $id)->count())->toBe(2)
        // El viejo no se anula al renovar: vence solo (decisión 3).
        ->and($viejo->vence_at->equalTo(
            $viejo->emitido_at->addSeconds(config('credenciales.ttl_seconds')),
        ))->toBeTrue();
});

it('switches to used and stops issuing codes when the cashier delivers it', function (): void {
    $id = $this->datos->id('kilo_rancho');
    $pantalla = Livewire::test(UsarBeneficio::class, ['beneficio' => (string) $id])->call('renovar');

    DB::table('redenciones')->insert([
        'cliente_id' => $this->datos->cliente->id,
        'beneficio_id' => $id,
        'comercio_id' => $this->datos->beneficios['kilo_rancho']->comercio_id,
        'valor_otorgado_cop' => 10000,
        'validado_por' => User::factory()->create()->id,
        'referencia' => (string) Illuminate\Support\Str::uuid(),
        'created_at' => CarbonImmutable::now(),
    ]);

    $pantalla->call('renovar')
        ->assertSet('codigo', null)
        ->assertSee('Ya lo usaste este mes');

    expect(CredencialQr::query()->where('beneficio_id', $id)->count())->toBe(1);
});

it('answers 404 to a benefit the customer cannot open', function (string $variante): void {
    $beneficio = $this->datos->beneficios['martes_bbc'];

    $id = $beneficio->id;

    match ($variante) {
        'inactive' => $beneficio->forceFill(['activo' => false])->save(),
        'inactive_merchant' => Comercio::query()->whereKey($beneficio->comercio_id)->update(['activo' => false]),
        'level_does_not_receive_it' => $id = Beneficio::query()->forceCreate([
            ...$beneficio->only(['comercio_id', 'tipo', 'condiciones', 'vigente_desde', 'activo']),
            'nombre' => 'Solo Bronce',
            'valores_por_nivel' => ['bronce' => 10],
        ])->id,
        default => $id = 999999,
    };

    $this->get(route('cliente.beneficio', $id))->assertNotFound();
})->with(fn (): array => collect(CatalogoTestData::fixture()['not_found_cases'])
    ->mapWithKeys(fn (array $caso): array => [$caso['_description'] => [$caso['variant']]])
    ->all());

it('never says canjear, costo or saldo', function (): void {
    $html = Livewire::test(Beneficios::class)->html()
        . Livewire::test(UsarBeneficio::class, ['beneficio' => (string) $this->datos->id('martes_bbc')])->html();

    foreach ($this->fixture['forbidden_words'] as $palabra) {
        expect(mb_strtolower($html))->not->toContain($palabra);
    }
});

it('links each catalog row to its detail', function (): void {
    $this->get(route('cliente.niveles'))->assertSee(route('cliente.beneficios'));
    $this->get(route('cliente.beneficios'))
        ->assertOk()
        ->assertSee(route('cliente.beneficio', $this->datos->id('martes_bbc')));
});

it('sends a guest to the sign-in screen', function (string $ruta): void {
    auth('cliente')->logout();

    $this->get($ruta === 'detalle' ? '/beneficios/1' : '/beneficios')->assertRedirect(route('cliente.ingreso'));
})->with(['catalogo', 'detalle']);

it('stops issuing codes past the limit per minute and shows the error state', function (): void {
    $tope = config('credenciales.max_benefit_codes_per_minute');
    $id = (string) $this->datos->id('martes_bbc');
    $pantalla = Livewire::test(UsarBeneficio::class, ['beneficio' => $id]);

    foreach (range(1, $tope) as $vez) {
        $pantalla->call('renovar')->assertSet('error', false);
    }

    $pantalla->call('renovar')->assertSet('error', true)->assertSet('codigo', null);

    expect(CredencialQr::query()->where('beneficio_id', $id)->count())->toBe($tope);

    $this->travel(61)->seconds();
    $pantalla->call('renovar')->assertSet('error', false);

    expect($pantalla->get('codigo'))->not->toBeNull();
});
