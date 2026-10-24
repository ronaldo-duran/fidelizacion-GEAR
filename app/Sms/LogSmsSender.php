<?php

declare(strict_types=1);

namespace App\Sms;

use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Escribe el mensaje en el log de desarrollo para probar el registro a mano.
 * Solo en el entorno `local`: en cualquier otro se niega, porque el mensaje
 * lleva el código de verificación.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $celular, string $mensaje): void
    {
        if (! app()->environment('local')) {
            throw new LogicException('El envío de SMS al log solo corre en el entorno local.');
        }

        Log::info("SMS para {$celular}: {$mensaje}");
    }
}
