<?php

declare(strict_types=1);

namespace App\Api;

use RuntimeException;

/**
 * Un error de la API pública con su estado HTTP y su código estable (E9-01,
 * regla 14). Lo lanzan las Actions y los middleware de la API cuando el
 * error no sale de una excepción de Laravel o del dominio.
 */
final class ErrorApi extends RuntimeException
{
    public readonly int $estado;

    public readonly string $codigo;

    /** @var array<string, list<string>> */
    public readonly array $campos;

    /**
     * @param  array<string, list<string>>  $campos
     */
    public function __construct(int $estado, string $codigo, string $mensaje, array $campos = [])
    {
        parent::__construct($mensaje);
        $this->estado = $estado;
        $this->codigo = $codigo;
        $this->campos = $campos;
    }

    public static function compraNoEncontrada(): self
    {
        return new self(404, 'compra_no_encontrada', 'No encontramos una compra con esa referencia en este comercio.');
    }
}
