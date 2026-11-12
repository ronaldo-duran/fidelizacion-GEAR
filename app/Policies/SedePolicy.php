<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sede;
use App\Models\User;
use App\Panel\AlcancePanel;

/**
 * Las sedes en el panel (E2-02). El administrador del grupo, todas; el de un
 * comercio, solo las de su comercio, y solo ahí las crea. Una sede no cambia
 * de comercio: sus compras y redenciones ya quedaron con ese comercio. Nadie
 * la borra: se desactiva.
 */
class SedePolicy
{
    public function viewAny(User $user): bool
    {
        return AlcancePanel::esAdministrador($user);
    }

    public function view(User $user, Sede $sede): bool
    {
        return $this->viewAny($user) && $user->alcanzaComercio($sede->comercio_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Guardar una sede en ese comercio, nueva o editada.
     */
    public function save(User $user, int $comercioId): bool
    {
        return $this->viewAny($user) && $user->alcanzaComercio($comercioId);
    }

    public function update(User $user, Sede $sede): bool
    {
        return $this->save($user, $sede->comercio_id);
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }
}
