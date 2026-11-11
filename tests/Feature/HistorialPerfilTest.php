<?php

declare(strict_types=1);

use App\Actions\Puntos\RecalculateSaldo;
use App\Livewire\Cliente\Historial;
use App\Puntos\FilaHistorial;
use App\Puntos\HistorialCliente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\HistorialTestData;

// Historial y perfil del cliente (E8-05). El cliente, sus movimientos y lo que se espera ver salen
// de tests/Fixtures/historial-perfil-escenarios.json.

beforeEach(function (): void {
    $this->fixture = HistorialTestData::fixture();
    $this->travelTo(CarbonImmutable::parse($this->fixture['today']));
    HistorialTestData::acumulacionRegla();
});

it('keeps the history config equal to the fixture', function (): void {
    expect(config('perfil.aviso_vencimiento_dias'))->toBe($this->fixture['history']['expiry_notice_days']['value']);
});

it('shows the balance, the expiry notice and the rows of the window grouped by month', function (): void {
    $esperado = $this->fixture['expected_history'];
    $historial = app(HistorialCliente::class)->para(HistorialTestData::cliente());

    $meses = array_map(
        static fn (array $filas): array => array_map(static fn (FilaHistorial $fila): array => [
            'title' => $fila->titulo,
            'line' => $fila->linea,
            'points' => $fila->puntos,
            'color' => $fila->suma ? 'success-ink' : 'danger',
        ], $filas),
        $historial->meses,
    );
    $esperados = collect($esperado['months'])->mapWithKeys(static fn (array $mes): array => [
        $mes['label'] => array_map(
            static fn (array $fila): array => array_diff_key($fila, ['key' => true]),
            $mes['rows'],
        ),
    ])->all();

    expect($historial->titulo)->toBe($esperado['title'])
        ->and($historial->saldo)->toBe($esperado['saldo_puntos'])
        ->and(implode(' ', $historial->avisoVencimiento ?? []))->toBe($esperado['expiry_notice'])
        ->and($meses)->toBe($esperados);
});

it('matches the balance with the ledger', function (): void {
    $cliente = HistorialTestData::cliente();
    $check = app(RecalculateSaldo::class)($cliente->id);

    expect($check->matches())->toBeTrue()
        ->and(app(HistorialCliente::class)->para($cliente)->saldo)->toBe($check->calculated);
});

it('shows no expiry notice when no lot expires inside the window', function (): void {
    $caso = $this->fixture['expiry_notice_outside_window'];
    $this->travelTo(CarbonImmutable::parse($caso['today']));
    $antes = array_values(array_filter(
        $this->fixture['movements'],
        static fn (array $movimiento): bool => CarbonImmutable::parse($movimiento['at'])->lte(CarbonImmutable::now()),
    ));

    expect(app(HistorialCliente::class)->para(HistorialTestData::cliente($antes))->avisoVencimiento)
        ->toBe($caso['expected_notice']);
});

it('renders the history screen without forbidden words', function (): void {
    $this->actingAs(HistorialTestData::cliente(), 'cliente');
    $esperado = $this->fixture['expected_history'];

    $html = $this->get(route('cliente.historial'))
        ->assertOk()
        ->assertSee($esperado['title'])
        ->assertSee('917')
        ->assertSee('Noviembre de 2025')
        ->assertSee('−32')
        ->assertDontSee('Compra registrada a mano')
        ->getContent();

    foreach ($esperado['forbidden_words'] as $palabra) {
        expect(mb_strtolower((string) $html))->not->toContain(mb_strtolower($palabra));
    }
});

it('shows the empty state to a customer without movements', function (): void {
    $this->actingAs(HistorialTestData::cliente([]), 'cliente');

    Livewire::test(Historial::class)
        ->assertSee($this->fixture['history']['empty_state']['title'])
        ->assertSee($this->fixture['history']['empty_state']['body']);
});

it('makes the same number of queries with 3 and with 30 movements', function (): void {
    $consultas = function (int $cantidad): int {
        $movimiento = $this->fixture['movements'][4];
        $movimientos = array_map(static fn (int $i): array => [
            ...$movimiento,
            'key' => "compra-{$i}",
            'at' => CarbonImmutable::parse('2026-10-01 10:00:00')->addMinutes($i)->toDateTimeString(),
        ], range(1, $cantidad));
        $cliente = HistorialTestData::cliente($movimientos, "99000{$cantidad}");

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(HistorialCliente::class)->para($cliente);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($consultas(30))->toBe($consultas(3));
});

it('sends a guest to the login page', function (string $ruta): void {
    $this->get(route($ruta))->assertRedirect(route('cliente.ingreso'));
})->with(['cliente.historial']);
