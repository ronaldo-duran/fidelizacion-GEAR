<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Qué se hizo con una notificación de la pasarela (E6-01). Toda
 * notificación queda registrada, válida o no.
 */
enum ResultadoNotificacionPago: string implements HasLabel
{
    case Aplicado = 'aplicado';
    case Repetido = 'repetido';
    case Rechazado = 'rechazado';
    case FirmaInvalida = 'firma_invalida';
    case ReferenciaDesconocida = 'referencia_desconocida';
    case MontoDistinto = 'monto_distinto';
    case TransicionInvalida = 'transicion_invalida';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aplicado => 'Aplicado',
            self::Repetido => 'Repetido',
            self::Rechazado => 'Pago rechazado',
            self::FirmaInvalida => 'Firma inválida',
            self::ReferenciaDesconocida => 'Referencia desconocida',
            self::MontoDistinto => 'Monto distinto',
            self::TransicionInvalida => 'Para revisión',
        };
    }
}
