<?php

declare(strict_types=1);

use App\Actions\Compras\AnnulCompra;
use App\Enums\EstadoTransaccion;
use App\Enums\TipoMovimiento;
use App\Filament\Resources\Transacciones\Pages\ListTransacciones;
use App\Filament\Resources\Transacciones\TransaccionResource;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use App\Models\User;
use App\Puntos\PlazoReversa;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\PanelClientesTestData;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->fixture = PanelClientesTestData::fixture();
    $this->travelTo(CarbonImmutable::parse($this->fixture['today']));
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->clientes = PanelClientesTestData::seed($this->admin);
    $this->usuario = fn (string $rol, ?string $nit = null): User => $rol === 'administrador_grupo'
            ? $this->admin
            : User::factory()->administradorDeComercio(PanelClientesTestData::comercio((string) $nit)->id)->create();
    $this->compra = fn (array $compra): Transaccion => Transaccion::query()
        ->where('cliente_id', $this->clientes[$compra['customer']]->id)
        ->where('comercio_id', PanelClientesTestData::comercio($compra['comercio_nit'])->id)
        ->sole();
});

it('keeps the reversal window of the ledger fixture', function (): void {
    $movimientos = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/movimientos-escenarios.json'), true);

    expect(PlazoReversa::dias())->toBe($movimientos['reversal_window_days']);
});

it('shows each role only its purchases, without the merchant filter for a merchant', function (array $caso): void {
    $usuario = ($this->usuario)($caso['role'], $caso['comercio_nit'] ?? null);
    $this->actingAs($usuario);
    $propias = Transaccion::query()->get()->filter(static fn (Transaccion $compra): bool => $usuario
        ->esAdministradorDelGrupo() || $compra->comercio_id === $usuario->comercio_id);

    $lista = Livewire::test(ListTransacciones::class)
        ->assertCanSeeTableRecords($propias)
        ->assertCanNotSeeTableRecords(Transaccion::query()->whereNotIn('id', $propias->modelKeys())->get());

    expect($propias)->toHaveCount($caso['purchases']);

    $caso['sees_merchant_filter']
        ? $lista->assertTableFilterVisible('comercio_id')
        : $lista->assertTableFilterHidden('comercio_id');

    foreach (Transaccion::query()->whereNotIn('id', $propias->modelKeys())->get() as $ajena) {
        expect(Gate::forUser($usuario)->allows('view', $ajena))->toBeFalse();
    }
})->with(PanelClientesTestData::dataset('visibility', static fn (array $caso): string => $caso['role']));

it('lists each purchase with merchant, customer, pesos, state, channel and who registered it', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ListTransacciones::class)
        ->assertSee('$450.000')
        ->assertSee('CC 1023456789 · Marcela Gómez')
        ->assertSee('Acreditada')
        ->assertSee('Cajero')
        ->assertSee('ref-marcela-800200200')
        ->assertSee($this->admin->name);
});

it('filters by period, merchant and state', function (): void {
    $this->actingAs($this->admin);
    $bbc = PanelClientesTestData::comercio('800200200');
    $recientes = Transaccion::query()->where('created_at', '>=', CarbonImmutable::today()->subDays(5))->get();
    $viejas = Transaccion::query()->whereNotIn('id', $recientes->modelKeys())->get();
    $anulada = ($this->compra)(['customer' => 'yeferson', 'comercio_nit' => '800100100']);
    app(AnnulCompra::class)($this->admin, $anulada->id, 'Prueba de filtro');

    Livewire::test(ListTransacciones::class)
        ->filterTable('periodo', ['desde' => CarbonImmutable::today()->subDays(5)->toDateString(), 'hasta' => null])
        ->assertCanSeeTableRecords($recientes)
        ->assertCanNotSeeTableRecords($viejas)
        ->resetTableFilters()
        ->filterTable('comercio_id', $bbc->id)
        ->assertCanSeeTableRecords(Transaccion::query()->where('comercio_id', $bbc->id)->get())
        ->assertCanNotSeeTableRecords(Transaccion::query()->where('comercio_id', '!=', $bbc->id)->get())
        ->resetTableFilters()
        ->filterTable('estado', EstadoTransaccion::Anulada->value)
        ->assertCanSeeTableRecords([$anulada])
        ->assertCanNotSeeTableRecords(Transaccion::query()->whereKeyNot($anulada->id)->get());
});

it('offers and runs each annulment of the fixture only within its rules', function (array $caso): void {
    $usuario = ($this->usuario)($caso['role'], $caso['comercio_nit'] ?? null);
    $compra = ($this->compra)($caso['purchase']);
    $esperado = $caso['expected'];

    if (($caso['purchase']['estado'] ?? null) === 'anulada') {
        app(AnnulCompra::class)($this->admin, $compra->id, 'Anulada antes');
    }

    $this->actingAs($usuario);
    $lista = Livewire::test(ListTransacciones::class);

    if (! $usuario->esAdministradorDelGrupo() && $compra->comercio_id !== $usuario->comercio_id) {
        // La compra de otro comercio ni siquiera está en su lista.
        $lista->assertCanNotSeeTableRecords([$compra]);
    } elseif ($esperado['action_visible']) {
        $lista->assertTableActionVisible('anular', $compra);
    } else {
        $lista->assertTableActionHidden('anular', $compra);
    }

    if (! array_key_exists('accepted', $esperado)) {
        return;
    }

    $antes = MovimientoPuntos::query()->count();

    if (! $esperado['action_visible']) {
        $rechazo = ($esperado['status'] ?? null) === 403 ? AuthorizationException::class : ValidationException::class;

        expect(fn (): Transaccion => app(AnnulCompra::class)($usuario, $compra->id, $caso['motivo']))
            ->toThrow($rechazo)
            ->and($compra->fresh()?->estado)->not->toBe(EstadoTransaccion::Anulada)
            ->and(MovimientoPuntos::query()->count())->toBe($antes);

        return;
    }

    $lista->callTableAction('anular', $compra, ['motivo' => $caso['motivo']]);

    if (! $esperado['accepted']) {
        $lista->assertHasTableActionErrors([$esperado['error_field']]);

        expect($compra->fresh()?->estado)->not->toBe(EstadoTransaccion::Anulada)
            ->and(MovimientoPuntos::query()->count())->toBe($antes);

        return;
    }

    $reversas = MovimientoPuntos::query()
        ->where('transaccion_id', $compra->id)
        ->where('tipo', TipoMovimiento::Reversa->value)
        ->get();

    expect($compra->fresh()?->estado)->toBe(EstadoTransaccion::from($esperado['estado']))
        ->and($reversas->sum('puntos'))->toBe($esperado['reversa_puntos'])
        ->and($reversas->first()?->motivo)->toBe($caso['motivo']);
})->with(PanelClientesTestData::dataset(
    'annulments',
    static fn (array $caso): string => $caso['_description'],
));

it('never lets anyone edit or delete a purchase from the panel', function (): void {
    $compra = Transaccion::query()->firstOrFail();

    expect(Gate::forUser($this->admin)->allows('update', $compra))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('delete', $compra))->toBeFalse()
        ->and(TransaccionResource::hasPage('create'))->toBeFalse()
        ->and(TransaccionResource::hasPage('edit'))->toBeFalse();
});

it('keeps the number of queries of the purchases list flat as rows grow', function (): void {
    $this->actingAs($this->admin);
    $consultas = static function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ListTransacciones::class);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $antes = $consultas();

    foreach (Transaccion::query()->get() as $i => $compra) {
        Transaccion::factory()->create([
            'comercio_id' => $compra->comercio_id,
            'sede_id' => $compra->sede_id,
            'cliente_id' => $compra->cliente_id,
            'referencia_externa' => "extra-{$i}",
            'registrada_por' => $this->admin->id,
        ]);
    }

    expect($consultas())->toBe($antes);
});
