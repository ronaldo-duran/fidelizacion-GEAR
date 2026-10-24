<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Para qué sirve una credencial QR: que el cajero reconozca al cliente para
 * registrar una compra, o que el cliente use un beneficio. Un código de uno
 * nunca sirve para el otro.
 */
enum PropositoCredencial: string
{
    case Identificar = 'identificar';
    case Beneficio = 'beneficio';
}
