<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;

/**
 * La ventana del límite por cliente de un beneficio (E5-01). Mes y año son
 * calendario en America/Bogota; de por vida cuenta todas las redenciones de
 * la cuenta y no vuelve.
 */
enum VentanaBeneficio: string implements HasLabel
{
    case Mes = 'mes';
    case Anio = 'anio';
    case Vida = 'vida';

    /**
     * Desde cuándo cuentan los usos de la ventana que contiene ese día; null
     * de por vida.
     */
    public function inicio(CarbonImmutable $dia): ?CarbonImmutable
    {
        return match ($this) {
            self::Mes => $dia->startOfMonth(),
            self::Anio => $dia->startOfYear(),
            self::Vida => null,
        };
    }

    /**
     * El primer día de la ventana siguiente ("Vuelve el 1 de noviembre"); null de por vida.
     */
    public function vuelve(CarbonImmutable $dia): ?CarbonImmutable
    {
        return match ($this) {
            self::Mes => $dia->startOfMonth()->addMonth(),
            self::Anio => $dia->startOfYear()->addYear(),
            self::Vida => null,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Mes => 'Por mes',
            self::Anio => 'Por año',
            self::Vida => 'De por vida',
        };
    }
}
