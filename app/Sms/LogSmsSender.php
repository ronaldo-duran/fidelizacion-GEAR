<?php

declare(strict_types=1);

namespace App\Sms;

use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Writes the message to the development log so the registration can be tried
 * by hand. Only in the `local` environment: anywhere else it refuses, because
 * the message carries the verification code.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $celular, string $mensaje): void
    {
        if (! app()->environment('local')) {
            throw new LogicException('The log SMS driver only runs in the local environment.');
        }

        Log::info("SMS para {$celular}: {$mensaje}");
    }
}
