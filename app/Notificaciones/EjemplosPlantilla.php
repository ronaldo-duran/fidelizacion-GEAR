<?php

declare(strict_types=1);

namespace App\Notificaciones;

use App\Enums\EventoNotificacion;

/**
 * Valores de ejemplo para la vista previa de una plantilla en el panel
 * (E7-01). Solo los de la lista blanca del evento, y coherentes entre sí:
 * una membresía de ejemplo es Diamante, el único nivel con planes.
 */
final class EjemplosPlantilla
{
    private const VALORES = [
        'nombre' => 'Ana',
        'nivel' => 'Oro',
        'nivel_anterior' => 'Plata',
        'nivel_resultante' => 'Plata',
        'puntos_que_vencen' => '1.200',
        'fecha_vencimiento' => '10 de diciembre',
        'dias_antes' => '7',
        'plan' => 'Diamante mensual',
        'monto' => '$49.900',
        'vigente_hasta' => '3 de enero de 2027',
        'beneficio' => '10 % en tu cuenta',
        'comercio' => 'El Rancho de Javi',
        'valor' => '$8.500',
        'fecha' => '4 de noviembre',
    ];

    /** Los valores que cambian según el aviso. */
    private const POR_EVENTO = [
        'membresia_confirmada' => ['nivel' => 'Diamante'],
        'membresia_por_vencer' => ['nivel' => 'Diamante'],
    ];

    /**
     * @return array<string, string>
     */
    public static function para(EventoNotificacion $evento): array
    {
        $ejemplos = [...self::VALORES, ...(self::POR_EVENTO[$evento->value] ?? [])];
        $valores = [];

        foreach ($evento->variables() as $variable) {
            $valores[$variable] = $variable === 'enlace'
                ? route('cliente.inicio')
                : $ejemplos[$variable] ?? $variable;
        }

        return $valores;
    }
}
