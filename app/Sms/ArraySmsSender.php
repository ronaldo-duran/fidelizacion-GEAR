<?php

declare(strict_types=1);

namespace App\Sms;

use LogicException;

/**
 * Guarda los mensajes en memoria en vez de enviarlos. Para las pruebas; se
 * niega a correr en producción, donde un mensaje se perdería sin aviso.
 */
class ArraySmsSender implements SmsSender
{
    /** @var list<array{celular: string, mensaje: string}> */
    private array $enviados = [];

    public function send(string $celular, string $mensaje): void
    {
        if (app()->environment('production')) {
            throw new LogicException('El envío de SMS en memoria no puede correr en producción.');
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
