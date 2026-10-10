<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Solo el administrador del grupo entra al tablero de Horizon fuera de
     * local: muestra las colas y los jobs nocturnos, que tocan puntos y
     * membresías. Los demás roles quedan fuera (regla de alcance de E2-01).
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static fn (?User $user = null): bool => $user !== null
            && $user->activo
            && $user->esAdministradorDelGrupo());
    }
}
