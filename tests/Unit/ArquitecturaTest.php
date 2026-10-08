<?php

/*
| Reglas de arquitectura que hacen cumplir CLAUDE.md de forma automática.
*/

// 3.8: nada de eval(), create_function ni evaluación dinámica de strings.
arch('sin funciones inseguras')
    ->preset()
    ->security();

arch('sin funciones de depuración olvidadas')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

// Cuando exista la primera clase en app/Actions, activar:
// arch('las acciones de dominio son invocables')
//     ->expect('App\Actions')
//     ->toBeInvokable();
