<?php

declare(strict_types=1);

use App\Enums\Nivel;
use App\Enums\TipoDocumento;
use App\Enums\TipoEventoCalificado;
use App\Filament\Resources\EventosCalificados\Pages\CreateEventoCalificado;
use App\Filament\Resources\EventosCalificados\Pages\ListEventosCalificados;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\EventoCalificado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\NivelesTestData;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-17 10:00:00'));
    NivelesTestData::nivelesRegla();
    $this->admin = User::factory()->administradorDelGrupo()->create();
    $this->colegio = Comercio::factory()->create([
        'categoria_id' => Categoria::query()->create(['nombre' => 'Colegio', 'slug' => 'colegio'])->id,
    ]);
    $this->cliente = NivelesTestData::cliente([]);
    $this->cliente->forceFill(['documento' => '1020304050'])->save();
});

it('registers a property purchase with its support file from the panel', function (): void {
    $this->actingAs($this->admin);
    $inmobiliaria = Comercio::factory()->create();

    Livewire::test(CreateEventoCalificado::class)
        ->fillForm([
            'documento' => '1020 304050',
            'tipo' => TipoEventoCalificado::CompraInmueble->value,
            'comercio_id' => $inmobiliaria->id,
            'vigente_desde' => '2026-10-17',
            'soporte_path' => UploadedFile::fake()->create('escritura.pdf', 200, 'application/pdf'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $evento = EventoCalificado::query()->sole();

    expect($evento->vigente_hasta->toDateString())->toBe('2027-10-17')
        ->and(Storage::disk('local')->exists($evento->soporte_path))->toBeTrue()
        ->and($this->cliente->fresh()?->nivel_efectivo)->toBe(Nivel::Diamante);
});

it('shows an unknown document without saving anything', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CreateEventoCalificado::class)
        ->fillForm([
            'documento' => '999',
            'tipo' => TipoEventoCalificado::MatriculaColegio->value,
            'comercio_id' => $this->colegio->id,
            'vigente_desde' => '2026-10-17',
            'vigente_hasta' => '2027-06-30',
            'soporte_path' => UploadedFile::fake()->create('matricula.pdf', 100, 'application/pdf'),
        ])
        ->call('create')
        ->assertNotified('No se registró el evento');

    expect(EventoCalificado::query()->count())->toBe(0);
});

it('finds the customer by document type and number, in any case', function (): void {
    $this->actingAs($this->admin);
    $venezolano = NivelesTestData::cliente([]);
    $venezolano->forceFill(['tipo_documento' => TipoDocumento::PPT, 'documento' => 'PPT55'])->save();
    $form = [
        'documento' => 'ppt 55',
        'tipo' => TipoEventoCalificado::MatriculaColegio->value,
        'comercio_id' => $this->colegio->id,
        'vigente_desde' => '2026-10-17',
        'vigente_hasta' => '2027-06-30',
        'soporte_path' => UploadedFile::fake()->create('matricula.pdf', 100, 'application/pdf'),
    ];

    Livewire::test(CreateEventoCalificado::class)
        ->fillForm([...$form, 'tipo_documento' => TipoDocumento::CE->value])
        ->call('create')
        ->assertNotified('No se registró el evento');

    Livewire::test(CreateEventoCalificado::class)
        ->assertSchemaStateSet(['tipo_documento' => TipoDocumento::CC])
        ->fillForm([...$form, 'tipo_documento' => TipoDocumento::PPT->value])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(EventoCalificado::query()->sole()->cliente_id)->toBe($venezolano->id);
});

it('lets the school administrator see and close only their enrollments', function (): void {
    $propio = NivelesTestData::evento(
        $this->cliente,
        ['tipo' => 'matricula_colegio', 'vigente_hasta' => 'hoy+8m'],
        $this->colegio,
    );
    $ajeno = NivelesTestData::evento($this->cliente, ['tipo' => 'matricula_colegio', 'vigente_hasta' => 'hoy+8m']);
    $this->actingAs(User::factory()->administradorDeComercio($this->colegio->id)->create());

    Livewire::test(ListEventosCalificados::class)
        ->assertCanSeeTableRecords([$propio])
        ->assertCanNotSeeTableRecords([$ajeno])
        ->callAction(TestAction::make('cerrar')->table($propio), data: [
            'fecha_cierre' => '2026-10-17',
            'motivo_cierre' => 'El niño se retiró',
        ])
        ->assertNotified('Evento cerrado');

    expect($propio->fresh()?->motivo_cierre)->toBe('El niño se retiró')
        ->and($propio->fresh()?->vigente_hasta->toDateString())->toBe('2026-10-17');
});

it('keeps cashiers out of the events page', function (): void {
    $this->actingAs(User::factory()->cajero($this->colegio->id)->create())
        ->get('/admin/eventos-calificados')
        ->assertForbidden();
});
