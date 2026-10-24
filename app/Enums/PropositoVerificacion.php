<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Para qué se envió un código de verificación. Un código de un propósito
 * nunca sirve para el otro.
 */
enum PropositoVerificacion: string
{
    case Registro = 'registro';
    case RecuperarPin = 'recuperar_pin';
}
