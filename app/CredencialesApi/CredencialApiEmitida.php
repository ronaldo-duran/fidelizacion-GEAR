<?php

declare(strict_types=1);

namespace App\CredencialesApi;

use App\Models\User;

/**
 * Una credencial de la API con el token recién emitido (E9-01, regla 4). El
 * token en claro solo existe aquí: se muestra una vez y no se guarda.
 */
final class CredencialApiEmitida
{
    public readonly User $credencial;

    public readonly string $token;

    public function __construct(User $credencial, string $token)
    {
        $this->credencial = $credencial;
        $this->token = $token;
    }
}
