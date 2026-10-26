<?php

declare(strict_types=1);

namespace App\Tablero;

/**
 * Lee los valores que devuelve una consulta cruda: cada motor entrega los
 * conteos y los ids como entero o como texto (MySQL y SQL Server, texto).
 */
final class ValorDeBase
{
    public static function entero(mixed $valor): int
    {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    public static function texto(mixed $valor): string
    {
        return is_string($valor) || is_int($valor) ? (string) $valor : '';
    }
}
