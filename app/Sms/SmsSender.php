<?php

declare(strict_types=1);

namespace App\Sms;

/**
 * Envía un SMS. La implementación se elige en `config/sms.php`; nunca debe
 * escribir el mensaje en un log fuera del entorno local, porque puede llevar
 * un código de verificación.
 */
interface SmsSender
{
    /**
     * @param  string  $celular  celular colombiano normalizado (10 dígitos)
     */
    public function send(string $celular, string $mensaje): void;
}
