<?php

declare(strict_types=1);

namespace App\Sms;

use RuntimeException;

/**
 * The Colombian SMS provider the client will choose (Inalambria, smsmasivo or
 * Háblame). Its HTTP call is written once the provider and its credentials
 * are known.
 */
class ProveedorSmsSender implements SmsSender
{
    public function send(string $celular, string $mensaje): void
    {
        // TODO(cliente): implementar la llamada HTTP del proveedor elegido con
        // config('sms.proveedor.url') y config('sms.proveedor.token').
        throw new RuntimeException('The SMS provider is not configured yet.');
    }
}
