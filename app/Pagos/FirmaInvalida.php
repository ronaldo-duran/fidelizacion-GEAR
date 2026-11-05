<?php

declare(strict_types=1);

namespace App\Pagos;

use RuntimeException;

/**
 * La notificación no trae una firma válida con el secreto de eventos de la
 * pasarela. Lleva el cuerpo recibido para dejarlo registrado.
 */
final class FirmaInvalida extends RuntimeException
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function __construct(public readonly array $payload)
    {
        parent::__construct('La notificación de la pasarela no tiene una firma válida.');
    }
}
