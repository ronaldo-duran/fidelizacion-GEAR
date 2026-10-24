<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a verification code was sent. A code for one purpose never works for
 * the other.
 */
enum PropositoVerificacion: string
{
    case Registro = 'registro';
    case RecuperarPin = 'recuperar_pin';
}
