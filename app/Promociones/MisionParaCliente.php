<?php

declare(strict_types=1);

namespace App\Promociones;

use App\Models\Regla;

/**
 * Una misión vigente con su estado para un cliente (E3-06, PWA):
 *
 * - disponible: el cliente puede enviar un cumplimiento; si el último que
 *   envió fue rechazado, `motivoRechazo` dice por qué.
 * - en revisión: tiene un cumplimiento pendiente de esta misión.
 * - completada: ya recibió los puntos todas las veces que la misión admite.
 */
final class MisionParaCliente
{
    public const DISPONIBLE = 'disponible';

    public const EN_REVISION = 'en_revision';

    public const COMPLETADA = 'completada';

    public readonly string $codigo;

    public readonly string $comercio;

    public readonly string $titulo;

    public readonly string $descripcion;

    public readonly int $puntos;

    public readonly int $maximo;

    public readonly bool $requiereSoporte;

    public readonly string $estado;

    /** Cuántas veces ya recibió los puntos de esta misión. */
    public readonly int $veces;

    public readonly ?string $motivoRechazo;

    public function __construct(Regla $mision, string $comercio, string $estado, int $veces, ?string $motivoRechazo)
    {
        $this->codigo = $mision->codigo;
        $this->comercio = $comercio;
        $this->titulo = self::texto($mision, 'titulo');
        $this->descripcion = self::texto($mision, 'descripcion');
        $this->puntos = self::entero($mision, 'puntos');
        $this->maximo = self::entero($mision, 'max_por_cliente');
        $this->requiereSoporte = ($mision->definicion['requiere_soporte'] ?? true) !== false;
        $this->estado = $estado;
        $this->veces = $veces;
        $this->motivoRechazo = $motivoRechazo;
    }

    /**
     * Un entero de la definición. El esquema cerrado (regla dura 3.8) ya lo
     * exige al guardar; aquí solo se acota el tipo.
     */
    public static function entero(Regla $mision, string $clave): int
    {
        $valor = $mision->definicion[$clave] ?? 0;

        return is_int($valor) ? $valor : 0;
    }

    private static function texto(Regla $mision, string $clave): string
    {
        $valor = $mision->definicion[$clave] ?? '';

        return is_string($valor) ? $valor : '';
    }
}
