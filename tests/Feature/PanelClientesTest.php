<?php

declare(strict_types=1);

use App\Actions\Puntos\ApplyAjusteManual;
use App\Enums\MotivoCambioNivel;
use App\Enums\Nivel;
use App\Enums\TipoMovimiento;
use App\Filament\Resources\Clientes\ClienteResource;
use App\Filament\Resources\Clientes\Pages\ListClientes;
use App\Filament\Resources\Clientes\Pages\ViewCliente;
use App\Filament\Resources\Clientes\RelationManagers\CambiosNivelRelationManager;
use App\Filament\Resources\Clientes\RelationManagers\MovimientosRelationManager;
use App\Models\CambioNivel;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Support\PanelClientesTestData;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->fixture = PanelClientesTestData::fixture();
    $this->travelTo(CarbonImmutable::parse($this->fixture['today']));
    $this->admin = User::factory()->administradorDelGrupo()->create(['name' => 'Ana Admin']);
    $this->clientes = PanelClientesTestData::seed($this->admin);
    $this->usuario = fn (string $rol, ?string $nit = null): User => $rol === 'administrador_grupo'
            ? $this->admin
            : User::factory()->administradorDeComercio(PanelClientesTestData::comercio((string) $nit)->id)->create();
    $this->libro = fn (Cliente $cliente): mixed => Livewire::test(MovimientosRelationManager::class, [
        'ownerRecord' => $cliente,
        'pageClass' => ViewCliente::class,
    ]);
});

it('derives the balance and the three level sources of each fixture customer', function (array $datos): void {
    $cliente = $this->clientes[$datos['key']];
    $esperado = $datos['expected'];

    expect($cliente->saldo_puntos)->toBe($esperado['saldo_puntos'])
        ->and($cliente->nivel_por_puntos)->toBe(Nivel::from($esperado['nivel_por_puntos']))
        ->and($cliente->nivel_efectivo)->toBe(Nivel::from($esperado['nivel_efectivo']))
        // Saldo y libro cuadran para el administrador del grupo (regla 5).
        ->and((int) MovimientoPuntos::query()->where('cliente_id', $cliente->id)->sum('puntos'))
        ->toBe($esperado['saldo_puntos']);
})->with(PanelClientesTestData::dataset('customers', static fn (array $caso): string => $caso['key']));

it('finds customers by normalized document with its type, and by name ignoring case', function (array $busqueda): void {
    $this->actingAs($this->admin);
    $esperados = array_map(fn (string $key): Cliente => $this->clientes[$key], $busqueda['expected']);
    $otros = array_diff_key($this->clientes, array_flip($busqueda['expected']));

    $lista = Livewire::test(ListClientes::class)->searchTable($busqueda['term']);

    if (isset($busqueda['tipo_documento'])) {
        $lista->filterTable('tipo_documento', $busqueda['tipo_documento']);
    }

    $lista->assertCanSeeTableRecords($esperados)->assertCanNotSeeTableRecords(array_values($otros));
})->with(PanelClientesTestData::dataset(
    'searches',
    static fn (array $caso): string => trim(($caso['tipo_documento'] ?? '') . ' ' . $caso['term']),
));

it('shows the three level sources apart, with an expired membership marked as expired', function (): void {
    $this->actingAs($this->admin);

    $this->get(ClienteResource::getUrl('view', ['record' => $this->clientes['marcela']]))
        ->assertSuccessful()
        ->assertSeeInOrder(['Por puntos', 'Plata', 'Por membresía', 'Diamante', 'hasta el 2026-09-30', 'Vencida'])
        ->assertSeeInOrder(['Por evento', 'Oro', 'hasta el 2027-03-31', 'Vigente'])
        ->assertSee('570 pts')
        ->assertDontSee('Canjear');
});

it('lists the ledger with the rule and version of each movement and the manual adjustment', function (): void {
    $this->actingAs($this->admin);
    $marcela = $this->clientes['marcela'];
    app(ApplyAjusteManual::class)($this->admin, $marcela->id, 10, 'Compra registrada en papel');

    ($this->libro)($marcela)
        ->assertCanSeeTableRecords(MovimientoPuntos::query()->where('cliente_id', $marcela->id)->get())
        ->assertSeeInOrder(['Ajuste manual', 'Compra registrada en papel', 'Ana Admin'])
        ->assertSee('acumulacion-base v1')
        ->assertSee('ref-marcela-800200200')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableActionDoesNotExist('create');
});

it('never lets anyone edit or delete a customer or a ledger movement', function (): void {
    $movimiento = MovimientoPuntos::query()->firstOrFail();
    $cliente = $this->clientes['marcela'];

    expect(Gate::forUser($this->admin)->allows('update', $movimiento))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('delete', $movimiento))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('view', $movimiento))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('create', Cliente::class))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('update', $cliente))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('delete', $cliente))->toBeFalse()
        ->and(ClienteResource::hasPage('create'))->toBeFalse()
        ->and(ClienteResource::hasPage('edit'))->toBeFalse();
});

it('applies each manual adjustment of the fixture through the ledger', function (array $caso): void {
    $cliente = $this->clientes[$caso['customer']];
    $usuario = ($this->usuario)($caso['role'], $caso['comercio_nit'] ?? null);
    $esperado = $caso['expected'];
    $this->actingAs($usuario);
    $antes = MovimientoPuntos::query()->count();
    $cambiosAntes = CambioNivel::query()->where('cliente_id', $cliente->id)->count();
    $pagina = Livewire::test(ViewCliente::class, ['record' => $cliente->getRouteKey()]);

    if (($esperado['status'] ?? null) === 403) {
        $pagina->assertActionHidden('ajustar');

        expect(fn (): MovimientoPuntos => app(ApplyAjusteManual::class)(
            $usuario,
            $cliente->id,
            $caso['puntos'],
            $caso['motivo'],
        ))->toThrow(AuthorizationException::class)
            ->and($cliente->fresh()?->saldo_puntos)->toBe($esperado['saldo_puntos']);

        return;
    }

    $pagina->callAction('ajustar', ['puntos' => $caso['puntos'], 'motivo' => $caso['motivo']]);
    $cliente->refresh();

    expect($cliente->saldo_puntos)->toBe($esperado['saldo_puntos']);

    if (! $esperado['accepted']) {
        expect(MovimientoPuntos::query()->count())->toBe($antes);

        return;
    }

    $movimiento = MovimientoPuntos::query()->latest('id')->firstOrFail();
    $cambios = CambioNivel::query()->where('cliente_id', $cliente->id)->latest('id')->get();

    expect($movimiento->only(['tipo', 'puntos', 'motivo', 'usuario_id', 'regla_id']))->toBe([
        'tipo' => TipoMovimiento::AjusteManual,
        'puntos' => $caso['puntos'],
        'motivo' => $caso['motivo'],
        'usuario_id' => $usuario->id,
        'regla_id' => null,
    ])
        ->and($cliente->nivel_por_puntos)->toBe(Nivel::from($esperado['nivel_por_puntos']))
        ->and($cliente->nivel_efectivo)->toBe(Nivel::from($esperado['nivel_efectivo']))
        ->and($cambios->count() - $cambiosAntes)->toBe($esperado['cambio_nivel'] === null ? 0 : 1);

    if ($esperado['cambio_nivel'] !== null) {
        expect($cambios->first()?->only(['nivel_anterior', 'nivel_nuevo', 'motivo']))->toBe([
            'nivel_anterior' => Nivel::from($esperado['cambio_nivel']['anterior']),
            'nivel_nuevo' => Nivel::from($esperado['cambio_nivel']['nuevo']),
            'motivo' => MotivoCambioNivel::from($esperado['cambio_nivel']['motivo']),
        ]);
    }
})->with(PanelClientesTestData::dataset(
    'adjustments',
    static fn (array $caso): string => "{$caso['customer']} {$caso['puntos']} ({$caso['role']})",
));

it('shows each role only the customers and movements in its scope', function (array $caso): void {
    $usuario = ($this->usuario)($caso['role'], $caso['comercio_nit'] ?? null);
    $this->actingAs($usuario);
    $visibles = array_map(fn (string $key): Cliente => $this->clientes[$key], $caso['customers']);
    $ocultos = array_values(array_diff_key($this->clientes, array_flip($caso['customers'])));

    Livewire::test(ListClientes::class)
        ->assertCanSeeTableRecords($visibles)
        ->assertCanNotSeeTableRecords($ocultos);

    foreach ($caso['ledger_of'] as $key => $libro) {
        $cliente = $this->clientes[$key];
        $movimientos = MovimientoPuntos::query()->where('cliente_id', $cliente->id)->get();
        $propios = $movimientos->filter(static fn (MovimientoPuntos $movimiento): bool => $usuario
            ->esAdministradorDelGrupo() || $movimiento->comercio_id === $usuario->comercio_id);

        expect($propios)->toHaveCount($libro['movements'])
            ->and($propios->sum('puntos') === $cliente->saldo_puntos)->toBe($libro['sum_equals_balance']);

        ($this->libro)($cliente)
            ->assertCanSeeTableRecords($propios)
            ->assertCanNotSeeTableRecords($movimientos->diffKeys($propios));

        // Ve el saldo y el nivel completos (decisión 4).
        $this->get(ClienteResource::getUrl('view', ['record' => $cliente]))
            ->assertSuccessful()
            ->assertSee(number_format($cliente->saldo_puntos, 0, ',', '.') . ' pts');
    }

    foreach ($caso['forbidden_customers'] ?? [] as $key) {
        $this->get(ClienteResource::getUrl('view', ['record' => $this->clientes[$key]]))->assertNotFound();
        expect(Gate::forUser($usuario)->allows('view', $this->clientes[$key]))->toBeFalse();
    }
})->with(PanelClientesTestData::dataset('visibility', static fn (array $caso): string => $caso['role']));

it('shows the level changes of the customer, newest first', function (): void {
    $this->actingAs($this->admin);
    $yeferson = $this->clientes['yeferson'];
    app(ApplyAjusteManual::class)($this->admin, $yeferson->id, 500, 'Compras de la sede nueva');

    Livewire::test(CambiosNivelRelationManager::class, ['ownerRecord' => $yeferson, 'pageClass' => ViewCliente::class])
        ->assertCanSeeTableRecords(CambioNivel::query()->where('cliente_id', $yeferson->id)->get())
        ->assertSeeInOrder(['Bronce', 'Plata', 'Ascenso por ajuste manual']);
});

it('keeps the number of queries flat as rows grow', function (): void {
    $this->actingAs($this->admin);
    $marcela = $this->clientes['marcela'];
    $consultas = static function (callable $render): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $render();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $lista = static fn (): mixed => Livewire::test(ListClientes::class);
    $libro = fn (): mixed => ($this->libro)($marcela);
    // Con un ajuste ya en el libro, la carga del usuario forma parte de la base.
    app(ApplyAjusteManual::class)($this->admin, $marcela->id, 1, 'Ajuste base');
    $antes = [$consultas($lista), $consultas($libro)];

    foreach (range(1, 3) as $i) {
        app(ApplyAjusteManual::class)($this->admin, $marcela->id, $i, "Ajuste {$i}");
        Cliente::query()->create(['tipo_documento' => 'CC', 'documento' => "99900000{$i}"]);
    }

    expect([$consultas($lista), $consultas($libro)])->toBe($antes);
});
