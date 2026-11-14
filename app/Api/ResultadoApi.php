<?php

declare(strict_types=1);

namespace App\Api;

/**
 * El recurso que respondió una escritura de la API y si fue un reintento que
 * devolvió lo que ya existía (E9-01, regla 7): 200 con
 * `Idempotent-Replayed: true` en lugar de 201.
 *
 * @template T
 */
final class ResultadoApi
{
    /** @var T */
    public readonly mixed $recurso;

    public readonly bool $repetido;

    /**
     * @param  T  $recurso
     */
    public function __construct(mixed $recurso, bool $repetido)
    {
        $this->recurso = $recurso;
        $this->repetido = $repetido;
    }
}
