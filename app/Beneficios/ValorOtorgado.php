<?php

declare(strict_types=1);

namespace App\Beneficios;

use App\Enums\TipoBeneficio;

/**
 * El valor en pesos que se entrega (regla dura 3.11). Un monto fijo vale su
 * monto; un porcentaje, ese porcentaje del monto de la cuenta, redondeado
 * hacia abajo porque el peso no tiene centavos (regla dura 3.9).
 */
final class ValorOtorgado
{
    public static function calcular(TipoBeneficio $tipo, int $valor, ?int $montoCuenta): int
    {
        return match ($tipo) {
            TipoBeneficio::MontoFijo => $valor,
            TipoBeneficio::Porcentaje => intdiv(($montoCuenta ?? 0) * $valor, 100),
        };
    }
}
