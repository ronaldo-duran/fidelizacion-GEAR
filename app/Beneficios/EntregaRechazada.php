<?php

declare(strict_types=1);

namespace App\Beneficios;

use RuntimeException;

/**
 * La entrega no se hizo porque el beneficio dejó de estar disponible o el
 * código ya no sirve (E5-02). Trae el resultado que el cajero ve; nada quedó
 * escrito y el código sigue sin gastar.
 */
final class EntregaRechazada extends RuntimeException
{
    public readonly ChequeoBeneficio $chequeo;

    public function __construct(ChequeoBeneficio $chequeo)
    {
        parent::__construct($chequeo->resultado->mensaje());
        $this->chequeo = $chequeo;
    }
}
