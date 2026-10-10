<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('niega el tablero de Horizon a un invitado', function (): void {
    expect(Gate::denies('viewHorizon'))->toBeTrue();
});

it('niega el tablero de Horizon a un administrador de comercio', function (): void {
    $comercio = User::factory()->administradorDeComercio()->create();

    expect(Gate::forUser($comercio)->allows('viewHorizon'))->toBeFalse();
});

it('niega el tablero de Horizon a un administrador del grupo desactivado', function (): void {
    $inactivo = User::factory()->administradorDelGrupo()->inactivo()->create();

    expect(Gate::forUser($inactivo)->allows('viewHorizon'))->toBeFalse();
});

it('deja ver el tablero de Horizon al administrador del grupo activo', function (): void {
    $grupo = User::factory()->administradorDelGrupo()->create();

    expect(Gate::forUser($grupo)->allows('viewHorizon'))->toBeTrue();
});
