<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Para las pruebas de concurrencia: RefreshDatabase no abre su transacción,
 * así una segunda conexión ve los datos de la prueba. Quien la use vacía la
 * base al terminar (BloqueoTestData::vaciar).
 */
trait SinTransaccionDePrueba
{
    /** @var list<string|null> */
    protected array $connectionsToTransact = [];
}
