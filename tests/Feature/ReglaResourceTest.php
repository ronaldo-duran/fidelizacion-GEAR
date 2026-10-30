<?php

declare(strict_types=1);

use App\Actions\Reglas\VersionRegla;
use App\Filament\Resources\Reglas\Pages\CreateRegla;
use App\Filament\Resources\Reglas\Pages\NewReglaVersion;
use App\Filament\Resources\Reglas\Pages\ViewRegla;
use App\Filament\Resources\Reglas\ReglaResource;
use App\Filament\Resources\Reglas\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Regla;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\ReglaTestData;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00:00'));
    $this->admin = User::factory()->administradorDelGrupo()->create();
});

it('shows the list, the detail and the new version page to the group administrator', function (): void {
    $regla = ReglaTestData::createBonificacion($this->admin);
    $this->actingAs($this->admin);

    $this->get('/admin/reglas')->assertSuccessful()->assertSee($regla->codigo);
    $this->get("/admin/reglas/{$regla->id}")->assertSuccessful()->assertSee($regla->codigo);
    $this->get("/admin/reglas/{$regla->id}/nueva-version")->assertSuccessful();
});

it('lists every version in the read-only history', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);
    $this->travel(1)->days();
    $v2 = app(VersionRegla::class)($this->admin, $v1, ReglaTestData::bonificacion(3));
    $this->actingAs($this->admin);

    Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $v2, 'pageClass' => ViewRegla::class])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$v1, $v2]);
});

it('returns 403 to a merchant administrator who tries to edit a rule', function (): void {
    $regla = ReglaTestData::createBonificacion($this->admin);
    $this->actingAs(User::factory()->administradorDeComercio()->create());

    $this->get('/admin/reglas')->assertForbidden();
    $this->get("/admin/reglas/{$regla->id}/nueva-version")->assertForbidden();
    $this->get('/admin/reglas/create')->assertForbidden();
});

it('never offers deleting rules', function (): void {
    $regla = ReglaTestData::createBonificacion($this->admin);
    $this->actingAs($this->admin);

    expect(ReglaResource::canDelete($regla))->toBeFalse()
        ->and(ReglaResource::canDeleteAny())->toBeFalse();
});

it('creates a niveles rule with the guided form', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CreateRegla::class)
        ->fillForm([
            'codigo' => 'niveles',
            'tipo' => 'niveles',
            'definicion' => [
                'umbrales' => [
                    ['nivel' => 'bronce', 'umbral_puntos' => '0'],
                    ['nivel' => 'plata', 'umbral_puntos' => '500'],
                    ['nivel' => 'oro', 'umbral_puntos' => '1500'],
                    ['nivel' => 'diamante', 'umbral_puntos' => '3000'],
                ],
                'proteccion_meses' => '0',
                'avisos_dias_antes' => [['dias' => '30'], ['dias' => '7'], ['dias' => '1']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $regla = Regla::query()->where('codigo', 'niveles')->sole();

    expect($regla->version)->toBe(1)
        ->and($regla->definicion['avisos_dias_antes'])->toBe([30, 7, 1])
        ->and($regla->definicion['umbrales'][3]['umbral_puntos'])->toBe(3000);
});

it('saves a new version from the form without touching the previous one', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);
    $this->actingAs($this->admin);
    $this->travel(1)->days();

    Livewire::test(NewReglaVersion::class, ['record' => $v1->getRouteKey()])
        ->fillForm([
            'definicion' => [
                'multiplicador' => '3',
                'condiciones' => [['campo' => 'dia_semana', 'operador' => 'en', 'valor_opciones' => ['6', '7']]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $v2 = Regla::query()->where('codigo', $v1->codigo)->where('version', 2)->sole();

    expect($v2->definicion['condiciones'][0]['valor'])->toBe([6, 7])
        ->and($v1->fresh()?->vigente_hasta)->not->toBeNull()
        ->and($v1->fresh()?->definicion)->toEqual(ReglaTestData::bonificacion());
});

it('does not save a version the engine rejects', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);
    $this->actingAs($this->admin);

    Livewire::test(NewReglaVersion::class, ['record' => $v1->getRouteKey()])
        ->fillForm([
            'definicion' => [
                'multiplicador' => '2',
                'condiciones' => [['campo' => 'monto', 'operador' => 'mayor', 'valor' => '49999.99']],
            ],
        ])
        ->call('save')
        ->assertNotified('No se guardó la regla');

    expect(Regla::query()->count())->toBe(1)
        ->and($v1->fresh()?->vigente_hasta)->toBeNull();
});

it('shows the JSON editor only to users with the permission', function (): void {
    $this->actingAs($this->admin);
    Livewire::test(CreateRegla::class)->assertFormFieldIsHidden('use_json_editor');

    $this->actingAs(User::factory()->administradorDelGrupo()->jsonReglaEditor()->create());
    Livewire::test(CreateRegla::class)->assertFormFieldIsVisible('use_json_editor');
});

it('creates a rule from the JSON editor with the permission', function (): void {
    $this->actingAs(User::factory()->administradorDelGrupo()->jsonReglaEditor()->create());

    Livewire::test(CreateRegla::class)
        ->fillForm([
            'codigo' => 'json',
            'tipo' => 'bonificacion',
            'use_json_editor' => true,
            'definicion_json' => json_encode(ReglaTestData::bonificacion(4)),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Regla::query()->where('codigo', 'json')->sole()->definicion)->toEqual(ReglaTestData::bonificacion(4));
});

it('shows the current version on the detail page', function (): void {
    $regla = ReglaTestData::createBonificacion($this->admin);
    $this->actingAs($this->admin);

    Livewire::test(ViewRegla::class, ['record' => $regla->getRouteKey()])
        ->assertSuccessful()
        ->assertSee($regla->codigo);
});

it('does not let mass assignment grant the JSON editor permission', function (): void {
    $user = User::factory()->administradorDelGrupo()->create();

    $user->update(['edita_reglas_json' => true]);

    expect($user->fresh()?->edita_reglas_json)->toBeFalse();
});

it('lets the group administrator grant the JSON editor permission from the user form', function (): void {
    $other = User::factory()->administradorDelGrupo()->create();
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $other->id])
        ->fillForm(['edita_reglas_json' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($other->fresh()?->edita_reglas_json)->toBeTrue();
});

it('picks a merchant condition value from a list and stores its id', function (): void {
    $v1 = ReglaTestData::createBonificacion($this->admin);
    $comercio = App\Models\Comercio::factory()->create(['razon_social' => 'Cervecería BBC']);
    $this->actingAs($this->admin);
    $this->travel(1)->days();

    Livewire::test(NewReglaVersion::class, ['record' => $v1->getRouteKey()])
        ->fillForm([
            'definicion' => [
                'multiplicador' => '2',
                'condiciones' => [
                    ['campo' => 'comercio_id', 'operador' => 'igual', 'valor_opcion' => (string) $comercio->id],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $v2 = Regla::query()->where('codigo', $v1->codigo)->where('version', 2)->sole();

    expect($v2->definicion['condiciones'][0])
        ->toEqual(['campo' => 'comercio_id', 'operador' => 'igual', 'valor' => $comercio->id]);

    $this->travel(1)->days();

    // La versión siguiente abre con el comercio ya elegido en la lista.
    $condiciones = Livewire::test(NewReglaVersion::class, ['record' => $v2->getRouteKey()])
        ->get('data.definicion.condiciones');

    expect((int) reset($condiciones)['valor_opcion'])->toBe($comercio->id);
});

it('shows the definition as readable lines in the "Vista" tab and as JSON in "Modo desarrollo"', function (): void {
    $regla = ReglaTestData::createBonificacion($this->admin);
    $this->actingAs($this->admin);

    $this->get(ReglaResource::getUrl('view', ['record' => $regla]))
        ->assertSuccessful()
        ->assertSee('Vista')
        ->assertSee('Modo desarrollo')
        ->assertSee('Multiplica los puntos por 2 cuando se cumple todo esto:')
        ->assertSee('El monto es mayor o igual que $50.000.')
        ->assertSee('mayor_igual');
});

it('creates a registration bonus with the guided form and shows it as readable lines', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(CreateRegla::class)
        ->fillForm([
            'codigo' => 'bono-lanzamiento',
            'tipo' => 'bono_registro',
            'definicion' => ['puntos' => '400', 'titulo' => '  Bono de lanzamiento '],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $regla = Regla::query()->where('codigo', 'bono-lanzamiento')->sole();

    expect($regla->definicion)->toBe(['puntos' => 400, 'titulo' => 'Bono de lanzamiento']);

    $this->get(ReglaResource::getUrl('view', ['record' => $regla]))
        ->assertSuccessful()
        ->assertSee('«Bono de lanzamiento».')
        ->assertSee('Da 400 puntos a quien se inscribe en el programa mientras rige.');
});
