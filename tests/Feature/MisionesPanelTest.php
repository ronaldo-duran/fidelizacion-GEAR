<?php

declare(strict_types=1);

use App\Actions\Promociones\SetPromocionLimites;
use App\Actions\Promociones\SubmitCumplimientoMision;
use App\Actions\Reglas\CreateRegla;
use App\Enums\EstadoCumplimiento;
use App\Enums\TipoMovimiento;
use App\Enums\TipoRegla;
use App\Filament\Resources\Comercios\Pages\EditComercio;
use App\Filament\Resources\CumplimientosMision\Pages\ListCumplimientosMision;
use App\Filament\Resources\Misiones\Pages\CreateMision;
use App\Filament\Resources\Misiones\Pages\ListMisiones;
use App\Filament\Resources\Misiones\Pages\NewMisionVersion;
use App\Filament\Resources\Misiones\Pages\ViewMision;
use App\Filament\Resources\Misiones\RelationManagers\MisionVersionsRelationManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Comercio;
use App\Models\ComercioPromocionLimite;
use App\Models\CumplimientoMision;
use App\Models\MovimientoPuntos;
use App\Models\Regla;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\LedgerTestData;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:00'));
    $this->admin = User::factory()->administradorDelGrupo()->create();
    LedgerTestData::acumulacionRegla();
    $this->cliente = LedgerTestData::cliente();
    $this->heladeria = Comercio::factory()->create();
    $this->colegio = Comercio::factory()->create();

    foreach ([$this->heladeria, $this->colegio] as $comercio) {
        app(SetPromocionLimites::class)($this->admin, $comercio->id, true, 100);
    }

    $this->mision = fn (Comercio $comercio, string $codigo, int $puntos = 10): Regla => app(CreateRegla::class)(
        $this->admin,
        $codigo,
        TipoRegla::Mision,
        [
            'comercio_id' => $comercio->id,
            'titulo' => 'Síguenos en Instagram',
            'descripcion' => 'Envía el pantallazo de que nos sigues.',
            'puntos' => $puntos,
            'max_por_cliente' => 1,
            'requiere_soporte' => true,
        ],
    );
    $this->enviar = fn (Regla $mision): CumplimientoMision => app(SubmitCumplimientoMision::class)(
        $this->cliente->id,
        $mision->codigo,
        'cumplimientos/pantallazo.png',
    );
    $this->revisor = fn (Comercio $comercio, bool $aprueba = true): User => User::factory()
        ->administradorDeComercio($comercio->id)
        ->state(['aprueba_misiones' => $aprueba])
        ->create();
    $this->formulario = [
        'titulo' => 'Síguenos en Instagram',
        'descripcion' => 'Envía el pantallazo de que nos sigues.',
        'puntos' => '10',
        'max_por_cliente' => '1',
        'requiere_soporte' => true,
    ];
});

it('lets the group create a mission for any merchant from the form', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CreateMision::class)
        ->fillForm([
            'codigo' => 'instagram-colegio',
            'definicion' => [...$this->formulario, 'comercio_id' => $this->colegio->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $mision = Regla::query()->where('codigo', 'instagram-colegio')->sole();

    expect($mision->tipo)->toBe(TipoRegla::Mision)
        ->and($mision->definicion['comercio_id'])->toBe($this->colegio->id)
        ->and($mision->definicion['puntos'])->toBe(10);
});

it('fixes the merchant of a merchant administrator and keeps the group limits', function (): void {
    $this->actingAs(User::factory()->administradorDeComercio($this->heladeria->id)->create());

    Livewire::test(CreateMision::class)
        ->assertSchemaStateSet(['definicion.comercio_id' => $this->heladeria->id])
        ->fillForm(['codigo' => 'instagram', 'definicion' => $this->formulario])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Regla::query()->where('codigo', 'instagram')->sole()->definicion['comercio_id'])
        ->toBe($this->heladeria->id);

    Livewire::test(CreateMision::class)
        ->fillForm(['codigo' => 'demasiado', 'definicion' => [...$this->formulario, 'puntos' => '101']])
        ->call('create')
        ->assertNotified('No se guardó la regla');

    expect(Regla::query()->where('codigo', 'demasiado')->exists())->toBeFalse();
});

it('shows a merchant administrator only the missions of its merchant', function (): void {
    $propia = ($this->mision)($this->heladeria, 'instagram');
    $ajena = ($this->mision)($this->colegio, 'matricula');
    $this->actingAs(User::factory()->administradorDeComercio($this->heladeria->id)->create());

    Livewire::test(ListMisiones::class)
        ->assertCanSeeTableRecords([$propia])
        ->assertCanNotSeeTableRecords([$ajena]);

    $this->get("/admin/misiones/{$propia->id}")->assertSuccessful()->assertSee('Síguenos en Instagram');
    $this->get("/admin/misiones/{$ajena->id}")->assertNotFound();
    $this->get('/admin/reglas')->assertForbidden();
});

it('keeps cashiers out of the missions page and missions out of the rules page', function (): void {
    $mision = ($this->mision)($this->heladeria, 'instagram');

    $this->actingAs($this->admin);
    $this->get('/admin/misiones')->assertSuccessful()->assertSee('Síguenos en Instagram');
    $this->get('/admin/reglas')->assertSuccessful()->assertDontSee($mision->codigo);

    $this->actingAs(User::factory()->cajero($this->heladeria->id)->create())
        ->get('/admin/misiones')
        ->assertForbidden();
});

it('saves a new version of a mission and shows its history to its merchant', function (): void {
    $v1 = ($this->mision)($this->heladeria, 'instagram');
    $this->travel(1)->days();
    $this->actingAs(User::factory()->administradorDeComercio($this->heladeria->id)->create());

    Livewire::test(NewMisionVersion::class, ['record' => $v1->id])
        ->assertSchemaStateSet(['definicion.comercio_id' => $this->heladeria->id])
        ->fillForm(['definicion.puntos' => '20'])
        ->call('save')
        ->assertHasNoFormErrors();

    $v2 = Regla::query()->where('codigo', 'instagram')->whereNull('vigente_hasta')->sole();

    expect($v2->version)->toBe(2)
        ->and($v2->definicion['puntos'])->toBe(20)
        ->and($v1->fresh()?->definicion['puntos'])->toBe(10);

    Livewire::test(MisionVersionsRelationManager::class, ['ownerRecord' => $v2, 'pageClass' => ViewMision::class])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$v1, $v2]);
});

it('shows the reviewer only the pending submissions of its merchant', function (): void {
    $propio = ($this->enviar)(($this->mision)($this->heladeria, 'instagram'));
    $ajeno = ($this->enviar)(($this->mision)($this->colegio, 'matricula'));
    $this->actingAs(($this->revisor)($this->heladeria));

    Livewire::test(ListCumplimientosMision::class)
        ->assertCanSeeTableRecords([$propio])
        ->assertCanNotSeeTableRecords([$ajeno]);
});

it('approves a submission from the queue and grants the points', function (): void {
    $cumplimiento = ($this->enviar)(($this->mision)($this->heladeria, 'instagram'));
    $revisor = ($this->revisor)($this->heladeria);
    $this->actingAs($revisor);

    Livewire::test(ListCumplimientosMision::class)
        ->callAction(TestAction::make('aprobar')->table($cumplimiento))
        ->assertNotified('Cumplimiento aprobado: se abonaron los puntos.');

    $movimiento = MovimientoPuntos::query()->where('cliente_id', $this->cliente->id)->sole();

    expect($cumplimiento->fresh()?->estado)->toBe(EstadoCumplimiento::Aprobado)
        ->and($cumplimiento->fresh()?->revisado_por)->toBe($revisor->id)
        ->and($movimiento->tipo)->toBe(TipoMovimiento::Promocion)
        ->and($movimiento->puntos)->toBe(10);
});

it('rejects a submission from the queue with its reason and grants nothing', function (): void {
    $cumplimiento = ($this->enviar)(($this->mision)($this->heladeria, 'instagram'));
    $this->actingAs(($this->revisor)($this->heladeria));

    Livewire::test(ListCumplimientosMision::class)
        ->callAction(TestAction::make('rechazar')->table($cumplimiento), data: ['motivo' => 'No se ve el perfil.'])
        ->assertNotified('Cumplimiento rechazado.');

    expect($cumplimiento->fresh()?->estado)->toBe(EstadoCumplimiento::Rechazado)
        ->and($cumplimiento->fresh()?->motivo_rechazo)->toBe('No se ve el perfil.')
        ->and(MovimientoPuntos::query()->where('cliente_id', $this->cliente->id)->exists())->toBeFalse();
});

it('downloads the support of a submission from the private disk', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('cumplimientos/pantallazo.png', 'imagen');
    $cumplimiento = ($this->enviar)(($this->mision)($this->heladeria, 'instagram'));
    $this->actingAs(($this->revisor)($this->heladeria));

    Livewire::test(ListCumplimientosMision::class)
        ->callAction(TestAction::make('soporte')->table($cumplimiento))
        ->assertFileDownloaded('pantallazo.png');
});

it('keeps the queue from users without the permission and from the group', function (): void {
    ($this->enviar)(($this->mision)($this->heladeria, 'instagram'));

    $this->actingAs(($this->revisor)($this->heladeria, false))->get('/admin/cumplimientos')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/cumplimientos')->assertForbidden();
    $this->actingAs(($this->revisor)($this->heladeria))->get('/admin/cumplimientos')->assertSuccessful();
});

it('lets the group edit the promotion limits of a merchant, and only the group', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(EditComercio::class, ['record' => $this->heladeria->id])
        ->assertSchemaStateSet(['promocion.permite_misiones' => true, 'promocion.puntos_max_por_mision' => 100])
        ->fillForm(['promocion' => ['permite_misiones' => true, 'puntos_max_por_mision' => '50']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ComercioPromocionLimite::query()->where('comercio_id', $this->heladeria->id)->sole()->puntos_max_por_mision)
        ->toBe(50);

    $this->actingAs(User::factory()->administradorDeComercio($this->heladeria->id)->create());

    Livewire::test(EditComercio::class, ['record' => $this->heladeria->id])
        ->assertSchemaComponentHidden('promocion', 'form')
        ->fillForm(['razon_social' => 'Heladería Renovada'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->heladeria->fresh()?->razon_social)->toBe('Heladería Renovada')
        ->and(ComercioPromocionLimite::query()->where('comercio_id', $this->heladeria->id)->sole()
            ->puntos_max_por_mision)->toBe(50);
});

it('lets the group grant the approval permission, never to a group administrator', function (): void {
    $deComercio = User::factory()->administradorDeComercio($this->heladeria->id)->create();
    $otroAdmin = User::factory()->administradorDelGrupo()->create();
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $deComercio->id])
        ->fillForm(['aprueba_misiones' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(EditUser::class, ['record' => $otroAdmin->id])
        ->assertFormFieldHidden('aprueba_misiones')
        ->fillForm(['aprueba_misiones' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($deComercio->fresh()?->aprueba_misiones)->toBeTrue()
        ->and($otroAdmin->fresh()?->aprueba_misiones)->toBeFalse()
        ->and(User::query()->make(['aprueba_misiones' => true])->aprueba_misiones)->not->toBeTrue();
});
