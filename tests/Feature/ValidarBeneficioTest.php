<?php

declare(strict_types=1);

use App\Livewire\Cajero\ValidarBeneficio;
use App\Models\Beneficio;
use App\Models\Cliente;
use App\Models\CredencialQr;
use App\Models\Redencion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\Support\BeneficiosTestData;
use Tests\Support\RedencionTestData;

// Pantalla "Validar beneficio" de la caja (E5-02): los pasos y lo que muestra; las reglas se prueban en RedencionTest.

beforeEach(function (): void {
    $fixture = RedencionTestData::fixture();
    $this->travelTo(CarbonImmutable::parse($fixture['today']));
    RedencionTestData::seed();
    $this->cajero = RedencionTestData::cajero('800100100');
    $this->andres = RedencionTestData::cliente(collect($fixture['customers'])->firstWhere('key', 'andres'));
    $this->kilo = BeneficiosTestData::beneficio('Kilo de carne en El Rancho de Javi');
});

it('opens from the mode switch of the till', function (): void {
    $this->actingAs($this->cajero)->get(route('caja.inicio'))
        ->assertOk()
        ->assertSee(route('caja.beneficios'));

    $this->actingAs($this->cajero)->get(route('caja.beneficios'))
        ->assertOk()
        ->assertSee('Validar beneficio')
        ->assertSee('Digitar código')
        ->assertDontSee('Linterna');

    $this->actingAs(User::factory()->administradorDelGrupo()->create())
        ->get(route('caja.beneficios'))
        ->assertForbidden();
});

it('shows VÁLIDO, asks for the bill and delivers the benefit with a scanned code', function (): void {
    $emitida = RedencionTestData::emitir($this->andres, $this->kilo);

    $pantalla = Livewire::actingAs($this->cajero)
        ->test(ValidarBeneficio::class)
        ->call('validarCodigo', $emitida->token)
        ->assertSet('paso', 'resultado')
        ->assertSee('VÁLIDO')
        ->assertSee('Kilo de carne en El Rancho de Javi')
        ->assertSee('20 % de descuento')
        ->assertSee('Andrés Rojas')
        ->assertSee('Plata')
        ->call('confirmar')
        ->assertSet('paso', 'datos')
        ->assertSee('Monto de la cuenta');

    expect(CredencialQr::query()->sole()->usado_at)->toBeNull();

    $pantalla->call('entregar', 200000)
        ->assertSet('paso', 'entregado')
        ->assertSee('Beneficio entregado')
        ->assertSee('$40.000');

    expect(Redencion::query()->sole()->valor_otorgado_cop)->toBe(40000)
        ->and(CredencialQr::query()->sole()->usado_at)->not->toBeNull();

    // El mismo código otra vez: YA USADO, con cuándo, dónde y que no vuelve.
    $pantalla->call('otro')
        ->call('validarCodigo', $emitida->token)
        ->assertSee('YA USADO')
        ->assertSee('Se usó el 29 de octubre, 3:00 p. m., en El Rancho de Javi · Sede principal.')
        ->assertSee('Es de una vez por cliente: no vuelve.');
});

it('validates a typed short code and keeps the code when the cashier cancels', function (): void {
    $emitida = RedencionTestData::emitir($this->andres, $this->kilo);

    Livewire::actingAs($this->cajero)
        ->test(ValidarBeneficio::class)
        ->set('digitar', true)
        ->assertSee('Buscar por documento')
        ->set('codigo', mb_strtolower($emitida->codigoCorto))
        ->call('validarCodigo')
        ->assertSee('VÁLIDO')
        ->call('otro')
        ->assertSet('paso', 'escaner');

    expect(CredencialQr::query()->sole()->usado_at)->toBeNull()
        ->and(Redencion::query()->count())->toBe(0);
});

it('shows NO VÁLIDO with the reason of an expired code', function (): void {
    $emitida = RedencionTestData::emitir($this->andres, $this->kilo);
    $this->travel(40)->seconds();

    Livewire::actingAs($this->cajero)
        ->test(ValidarBeneficio::class)
        ->call('validarCodigo', $emitida->token)
        ->assertSee('NO VÁLIDO')
        ->assertSee('El código venció. Pídele al cliente que lo actualice y escanéalo otra vez.')
        ->assertSee('Escanear otro');
});

it('lists the benefits by document and only lets the cashier choose an available one', function (): void {
    $postre = BeneficiosTestData::beneficio('Postre de la casa (solo Oro y Diamante)');

    $pantalla = Livewire::actingAs($this->cajero)
        ->test(ValidarBeneficio::class)
        ->call('porDocumento')
        ->assertSet('paso', 'documento')
        ->assertSee('Tipo de documento')
        ->set('form.documento', '1012 345 678')
        ->call('buscar')
        ->assertSet('paso', 'lista')
        ->assertSee('Andrés Rojas')
        ->assertSee('20 % de descuento')
        ->assertSee('Desde Oro')
        ->assertSeeHtml('wire:click="elegir(' . $this->kilo->id . ')"')
        ->assertDontSeeHtml('wire:click="elegir(' . $postre->id . ')"');

    // Un beneficio bloqueado no pasa a VÁLIDO aunque se pida por su id.
    $pantalla->call('elegir', $postre->id)->assertSee('NO VÁLIDO')->call('otro');

    $pantalla->call('porDocumento')
        ->set('form.documento', '1012345678')
        ->call('buscar')
        ->call('elegir', $this->kilo->id)
        ->assertSee('VÁLIDO')
        ->call('confirmar')
        ->call('entregar', 100000)
        ->call('entregar', 100000)
        ->assertSet('paso', 'entregado');

    expect(Redencion::query()->sole()->credencial_qr_id)->toBeNull();
});

it('says when a document is not registered', function (): void {
    Livewire::actingAs($this->cajero)
        ->test(ValidarBeneficio::class)
        ->call('porDocumento')
        ->set('form.tipoDocumento', 'CE')
        ->set('form.documento', '1012345678')
        ->call('buscar')
        ->assertHasErrors(['form.documento'])
        ->assertSee('No hay un cliente con ese documento.');
});

it('keeps the bill error on screen and shows the rejection when the benefit is gone', function (): void {
    $pantalla = Livewire::actingAs($this->cajero)
        ->test(ValidarBeneficio::class)
        ->call('porDocumento')
        ->set('form.documento', '1012345678')
        ->call('buscar')
        ->call('elegir', $this->kilo->id)
        ->call('confirmar')
        ->call('entregar', 4)
        ->assertHasErrors(['monto_cuenta'])
        ->assertSet('paso', 'datos');

    Beneficio::query()->toBase()->where('id', $this->kilo->id)->update(['activo' => false]);

    $pantalla->call('entregar', 100000)
        ->assertSee('NO VÁLIDO')
        ->assertSee('Este beneficio ya no está disponible.');

    expect(Redencion::query()->count())->toBe(0)
        ->and(Cliente::query()->sole()->saldo_puntos)->toBe(620);
});
