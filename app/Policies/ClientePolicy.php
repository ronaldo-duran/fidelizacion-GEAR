<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;
use App\Panel\AlcancePanel;

/**
 * Los clientes en el panel (E10-06): solo lectura, con el alcance de cada
 * rol. El cliente se registra en la PWA (E8-02); nadie lo crea, edita ni
 * borra desde aquí.
 */
class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return AlcancePanel::esAdministrador($user);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return AlcancePanel::esAdministrador($user) && AlcancePanel::veCliente($user, $cliente);
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
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
