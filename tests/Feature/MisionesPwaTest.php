<?php

declare(strict_types=1);

use App\Actions\Promociones\ApproveCumplimientoMision;
use App\Actions\Promociones\RejectCumplimientoMision;
use App\Actions\Promociones\SendCumplimientoMision;
use App\Actions\Promociones\SetPromocionLimites;
use App\Actions\Reglas\CreateRegla;
use App\Enums\EstadoCumplimiento;
use App\Enums\TipoRegla;
use App\Livewire\Cliente\Misiones;
use App\Models\Comercio;
use App\Models\CumplimientoMision;
use App\Models\Regla;
use App\Models\User;
use App\Promociones\MisionesParaCliente;
use App\Promociones\MisionParaCliente;
use Carbon\CarbonImmutable;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\LedgerTestData;

beforeEach(function (): void {
    $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/promociones-escenarios.json'), true);
    $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:00'));
    Storage::fake('local');
    // Un PNG real de 1×1: las pruebas no dependen de la extensión GD.
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    $this->pantallazo = static fn (string $nombre): File => UploadedFile::fake()
        ->createWithContent($nombre, (string) base64_decode($png, true));
    $this->admin = User::factory()->administradorDelGrupo()->create();
    LedgerTestData::acumulacionRegla();
    $this->cliente = LedgerTestData::cliente();
    $this->comercio = Comercio::factory()->create(['razon_social' => 'El Rancho de Javi']);
    app(SetPromocionLimites::class)($this->admin, $this->comercio->id, true, 50);

    // La misión de Instagram del fixture (10 puntos, una vez, con soporte).
    $instagram = collect($fixture['definitions']['valid'])
        ->first(static fn (array $regla): bool => $regla['tipo'] === 'mision');
    $this->mision = fn (string $codigo, array $cambios = [], ?int $comercioId = null): Regla => app(CreateRegla::class)(
        $this->admin,
        $codigo,
        TipoRegla::Mision,
        [...$instagram['definicion'], 'comercio_id' => $comercioId ?? $this->comercio->id, ...$cambios],
    );
    $this->revisor = User::factory()->cajero($this->comercio->id)->apruebaMisiones()->create();
    $this->inactivo = Comercio::factory()->create(['activo' => false]);
    app(SetPromocionLimites::class)($this->admin, $this->inactivo->id, true, 50);
    $this->actingAs($this->cliente, 'cliente');
});

it('asks a guest to sign in before showing the missions', function (): void {
    auth('cliente')->logout();

    $this->get('/misiones')->assertRedirect('/ingresar');
});

it('lists the missions in force of active merchants with their points', function (): void {
    ($this->mision)('instagram-rancho');
    ($this->mision)('instagram-inactivo', ['titulo' => 'Misión de un comercio inactivo'], $this->inactivo->id);

    $this->get('/misiones')
        ->assertOk()
        ->assertSee('Misiones')
        ->assertSee('Síguenos en Instagram')
        ->assertSee('El Rancho de Javi')
        ->assertSee('+10 pts')
        ->assertSee('Cumplir misión')
        ->assertDontSee('Misión de un comercio inactivo');

    $this->get('/inicio')->assertSee(route('cliente.misiones'), false);
});

it('shows an empty state when there are no missions', function (): void {
    $this->get('/misiones')->assertOk()->assertSee('No hay misiones por ahora');
});

it('sends the screenshot to review and stores it under a server-chosen name', function (): void {
    ($this->mision)('instagram-rancho');

    Livewire::test(Misiones::class)
        ->call('abrir', 'instagram-rancho')
        ->assertSee('Enviar a revisión')
        ->set('soporte', ($this->pantallazo)('../../mi pantallazo.png'))
        ->call('enviar')
        ->assertHasNoErrors()
        ->assertSet('abierta', null)
        ->assertSee('Enviamos tu misión a revisión.')
        ->assertSee('En revisión.');

    $cumplimiento = CumplimientoMision::query()->sole();
    expect($cumplimiento->estado)->toBe(EstadoCumplimiento::Pendiente)
        ->and($cumplimiento->cliente_id)->toBe($this->cliente->id)
        ->and($cumplimiento->soporte_path)->toStartWith('cumplimientos/' . $this->cliente->id . '/')
        ->and($cumplimiento->soporte_path)->not->toContain('pantallazo');
    Storage::disk('local')->assertExists((string) $cumplimiento->soporte_path);
});

it('rejects a missing screenshot or a file that is not an image', function (): void {
    ($this->mision)('instagram-rancho');

    Livewire::test(Misiones::class)
        ->call('abrir', 'instagram-rancho')
        ->call('enviar')
        ->assertHasErrors(['soporte'])
        ->assertSee('Adjunta el pantallazo que pide la misión.')
        ->set('soporte', UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'))
        ->call('enviar')
        ->assertHasErrors(['soporte'])
        ->assertSee('El soporte debe ser una imagen.')
        ->set('soporte', ($this->pantallazo)('enorme.png')->size(6000))
        ->call('enviar')
        ->assertSee('La imagen no puede pasar de 5 MB.');

    expect(CumplimientoMision::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('cumplimientos'))->toBe([]);
});

it('sends a mission that does not ask for a screenshot without a file', function (): void {
    ($this->mision)('visita-rancho', ['requiere_soporte' => false, 'titulo' => 'Visítanos']);

    Livewire::test(Misiones::class)
        ->call('abrir', 'visita-rancho')
        ->assertDontSee('Pantallazo')
        ->call('enviar')
        ->assertHasNoErrors()
        ->assertSee('En revisión.');

    expect(CumplimientoMision::query()->sole()->soporte_path)->toBeNull();
});

it('deletes the stored screenshot when the submission is not accepted', function (): void {
    ($this->mision)('instagram-rancho');
    $send = app(SendCumplimientoMision::class);
    $send($this->cliente->id, 'instagram-rancho', ($this->pantallazo)('uno.png'));

    expect(fn () => $send($this->cliente->id, 'instagram-rancho', ($this->pantallazo)('dos.png')))
        ->toThrow(ValidationException::class, 'Ya enviaste esta misión y está en revisión.');

    expect(Storage::disk('local')->allFiles('cumplimientos'))->toHaveCount(1)
        ->and(CumplimientoMision::query()->count())->toBe(1);

    Livewire::test(Misiones::class)
        ->call('abrir', 'instagram-rancho')
        ->set('soporte', ($this->pantallazo)('tres.png'))
        ->call('enviar')
        ->assertHasErrors(['soporte'])
        ->assertSee('En revisión.');

    expect(Storage::disk('local')->allFiles('cumplimientos'))->toHaveCount(1);
});

it('shows the reason of a rejection and lets the customer send again', function (): void {
    ($this->mision)('instagram-rancho');
    $cumplimiento = app(SendCumplimientoMision::class)(
        $this->cliente->id,
        'instagram-rancho',
        ($this->pantallazo)('uno.png'),
    );
    app(RejectCumplimientoMision::class)($this->revisor, $cumplimiento->id, 'No se ve la cuenta que sigues.');

    $this->get('/misiones')
        ->assertSee('El comercio rechazó tu último envío: No se ve la cuenta que sigues.')
        ->assertSee('Cumplir misión');
});

it('marks the mission completed once it granted its points the maximum times', function (): void {
    ($this->mision)('instagram-rancho');
    $cumplimiento = app(SendCumplimientoMision::class)(
        $this->cliente->id,
        'instagram-rancho',
        ($this->pantallazo)('uno.png'),
    );
    app(ApproveCumplimientoMision::class)($this->revisor, $cumplimiento->id);

    $this->get('/misiones')
        ->assertSee('Completada.')
        ->assertDontSee('Cumplir misión');
});

it('counts the progress of a mission with several occurrences across its versions', function (): void {
    ($this->mision)('matricula', ['titulo' => 'Matricula a un alumno', 'max_por_cliente' => 3]);
    $cumplimiento = app(SendCumplimientoMision::class)($this->cliente->id, 'matricula', ($this->pantallazo)('a.png'));
    app(ApproveCumplimientoMision::class)($this->revisor, $cumplimiento->id);

    [$item] = app(MisionesParaCliente::class)->para($this->cliente->id);

    expect($item->estado)->toBe(MisionParaCliente::DISPONIBLE)
        ->and($item->veces)->toBe(1)
        ->and($item->maximo)->toBe(3)
        ->and($item->motivoRechazo)->toBeNull();
    $this->get('/misiones')->assertSee('Llevas 1 de 3');
});

it('reads the missions with a fixed number of queries', function (): void {
    $consultas = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(MisionesParaCliente::class)->para($this->cliente->id);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    ($this->mision)('mision-uno');
    $una = $consultas();
    ($this->mision)('mision-dos');
    ($this->mision)('mision-tres');

    expect($consultas())->toBe($una)
        ->and(app(MisionesParaCliente::class)->para($this->cliente->id))->toHaveCount(3);
});
