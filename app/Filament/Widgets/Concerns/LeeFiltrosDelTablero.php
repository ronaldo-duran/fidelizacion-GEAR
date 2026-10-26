<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use App\Tablero\AlcanceTablero;
use App\Tablero\Periodo;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

/**
 * El periodo que eligió el selector del tablero y el alcance de quien lo mira
 * (el grupo o solo su comercio). Los widgets solo pintan: las cifras salen de
 * las consultas de `app/Tablero/`.
 */
trait LeeFiltrosDelTablero
{
    use InteractsWithPageFilters;

    protected function periodo(): Periodo
    {
        $mes = $this->pageFilters['periodo'] ?? null;

        return Periodo::desde(is_string($mes) ? $mes : null);
    }

    protected function alcance(): AlcanceTablero
    {
        $user = Auth::user();

        return $user instanceof User ? AlcanceTablero::para($user) : AlcanceTablero::comercio(0);
    }
}
