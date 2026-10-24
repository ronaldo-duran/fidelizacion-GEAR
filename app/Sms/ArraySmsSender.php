<?php

declare(strict_types=1);

namespace App\Sms;

use LogicException;

/**
 * Keeps the messages in memory instead of sending them. For tests; it refuses
 * to run in production, where a message would be silently lost.
 */
class ArraySmsSender implements SmsSender
{
    /** @var list<array{celular: string, mensaje: string}> */
    private array $enviados = [];

    public function send(string $celular, string $mensaje): void
    {
        if (app()->environment('production')) {
            throw new LogicException('The array SMS driver cannot run in production.');
        }

        $this->enviados[] = ['celular' => $celular, 'mensaje' => $mensaje];
    }

    /**
     * @return list<array{celular: string, mensaje: string}>
     */
    public function enviados(): array
    {
        return $this->enviados;
    }
}
