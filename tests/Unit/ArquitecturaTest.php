<?php

declare(strict_types=1);

/*
| Reglas de arquitectura que hacen cumplir CLAUDE.md de forma automática.
| La revisión de Claude cubre lo que no se puede expresar aquí.
*/

// 3.8: nada de eval(), create_function ni evaluación dinámica de strings.
arch('sin funciones inseguras')
    ->preset()
    ->security();

arch('sin depuración ni salidas abruptas')
    ->preset()
    ->php();

// declare(strict_types=1) y comparaciones estrictas los exige Pint (pint.json) en el CI.

// Los modelos son modelos: extienden Model y no conocen la capa HTTP, Filament ni Livewire.
arch('los modelos son modelos de Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('los modelos no dependen de la capa de presentación')
    ->expect('App\Models')
    ->not->toUse([
        'App\Http',
        'App\Filament',
        'App\Livewire',
        'Illuminate\Http\Request',
        'Livewire\Component',
        'Filament\Resources\Resource',
    ]);

// Controladores delgados: validan con Form Request y delegan en una Action.
arch('los controladores tienen sufijo Controller')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('los controladores no consultan la base de datos directamente')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\Query\Builder']);

arch('la configuración se lee con config(), no con env()')
    ->expect('env')
    ->toOnlyBeUsedIn(['config']);

// Cuando exista la primera clase en app/Actions, activar:
// arch('las acciones de dominio son invocables')
//     ->expect('App\Actions')
//     ->toBeInvokable();
