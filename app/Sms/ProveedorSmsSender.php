<?php

declare(strict_types=1);

namespace App\Sms;

use RuntimeException;

/**
 * El proveedor colombiano de SMS que elija el cliente (Inalambria, smsmasivo
 * o Háblame). Su llamada HTTP se escribe cuando se conozcan el proveedor y sus
 * credenciales.
 */
class ProveedorSmsSender implements SmsSender
{
    public function send(string $celular, string $mensaje): void
    {
        // TODO(cliente): implementar la llamada HTTP del proveedor elegido con
        // config('sms.proveedor.url') y config('sms.proveedor.token').
        throw new RuntimeException('El proveedor de SMS aún no está configurado.');
    }
}
