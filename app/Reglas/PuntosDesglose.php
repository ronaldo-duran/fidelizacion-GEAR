<?php

declare(strict_types=1);

namespace App\Reglas;

/**
 * How a purchase's points were reached: base points, the bonus applied, the
 * cap that limited them and the final points, each traced to its rule.
 */
final class PuntosDesglose
{
    public readonly ?ReglaAplicada $acumulacion;

    public readonly int $base;

    public readonly ?ReglaAplicada $bonificacion;

    public readonly int $multiplicador;

    public readonly ?string $tope;

    public readonly int $puntos;

    public function __construct(
        ?ReglaAplicada $acumulacion,
        int $base,
        ?ReglaAplicada $bonificacion,
        int $multiplicador,
        ?string $tope,
        int $puntos,
    ) {
        $this->acumulacion = $acumulacion;
        $this->base = $base;
        $this->bonificacion = $bonificacion;
        $this->multiplicador = $multiplicador;
        $this->tope = $tope;
        $this->puntos = $puntos;
    }

    public static function none(): self
    {
        return new self(null, 0, null, 1, null, 0);
    }

    /**
     * Final points that belong to the accrual rule. A cap cuts the bonus
     * points first, so the base keeps as much as the final points allow.
     */
    public function basePuntos(): int
    {
        return min($this->base, $this->puntos);
    }

    /**
     * Final points that belong to the bonus rule.
     */
    public function extraPuntos(): int
    {
        return $this->puntos - $this->basePuntos();
    }
}
