<?php

declare(strict_types=1);

namespace App\Sms;

/**
 * Sends one SMS. Implementations are chosen by `config/sms.php`; they must
 * never log the message outside the local environment, because it may carry
 * a verification code.
 */
interface SmsSender
{
    /**
     * @param  string  $celular  normalized Colombian mobile (10 digits)
     */
    public function send(string $celular, string $mensaje): void;
}
