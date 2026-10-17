<?php

declare(strict_types=1);

namespace App\Reglas;

use App\Enums\TipoRegla;
use LogicException;

/**
 * Points a purchase earns under a set of rules. Pure: it never touches the
 * database, so the simulator and the real registration of a purchase (E4-01)
 * get exactly the same numbers. Points are status, never a balance to spend
 * (hard rule 3.1), and always whole numbers (hard rule 3.9).
 *
 * TODO(cliente): the client confirmed the rates (1 point per $1.000) but not
 * how bonuses and caps combine. Until then:
 * - base points = floor(monto / moneda_por_punto) × puntos_por_unidad;
 * - only the highest bonus multiplier applies, bonuses never stack;
 * - order: base → bonus → per-transaction cap → daily cap → monthly cap.
 */
class PuntosCalculator
{
    private const TOPES = ['puntos_por_transaccion', 'puntos_por_dia', 'puntos_por_mes'];

    private readonly CondicionesEvaluator $condiciones;

    public function __construct(CondicionesEvaluator $condiciones)
    {
        $this->condiciones = $condiciones;
    }

    /**
     * @param  list<ReglaAplicada>  $reglas  accrual and bonus rules to apply
     * @param  int  $puntosDelDia  accrual points the customer already earned that day
     * @param  int  $puntosDelMes  accrual points the customer already earned that month
     */
    public function calculate(
        CompraData $compra,
        array $reglas,
        int $puntosDelDia = 0,
        int $puntosDelMes = 0,
    ): PuntosDesglose {
        $acumulacion = $this->acumulacion($reglas);

        if ($acumulacion === null) {
            return PuntosDesglose::none();
        }

        $base = $this->base($acumulacion->definicion, $compra);
        $bonificacion = $base > 0 ? $this->bonificacion($reglas, $compra) : null;
        $multiplicador = $bonificacion === null ? 1 : $this->multiplicador($bonificacion);
        $puntos = $base * $multiplicador;
        $tope = null;

        $limits = $this->limits($acumulacion->definicion, $puntosDelDia, $puntosDelMes);

        foreach ($limits as $name => $limit) {
            if ($puntos > $limit) {
                $puntos = $limit;
                $tope = $name;
            }
        }

        return new PuntosDesglose($acumulacion, $base, $bonificacion, $multiplicador, $tope, $puntos);
    }

    /**
     * @param  list<ReglaAplicada>  $reglas
     */
    private function acumulacion(array $reglas): ?ReglaAplicada
    {
        $acumulaciones = array_values(array_filter(
            $reglas,
            static fn (ReglaAplicada $regla): bool => $regla->tipo === TipoRegla::Acumulacion,
        ));

        if (count($acumulaciones) > 1) {
            throw new LogicException('Only one accrual rule can be in force at a time.');
        }

        return $acumulaciones[0] ?? null;
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     */
    private function base(array $definicion, CompraData $compra): int
    {
        $monedaPorPunto = $definicion['moneda_por_punto'] ?? null;
        $tasas = $definicion['tasas_por_categoria'] ?? null;

        if (! is_int($monedaPorPunto) || $monedaPorPunto < 1 || ! is_array($tasas) || $compra->monto < 1) {
            return 0;
        }

        foreach ($tasas as $tasa) {
            if (is_array($tasa) && ($tasa['categoria_slug'] ?? null) === $compra->categoriaSlug) {
                $puntosPorUnidad = $tasa['puntos_por_unidad'] ?? 0;

                return intdiv($compra->monto, $monedaPorPunto) * (is_int($puntosPorUnidad) ? $puntosPorUnidad : 0);
            }
        }

        return 0;
    }

    /**
     * The bonus with the highest multiplier among those whose conditions all
     * hold. On a tie, the first one in the given order wins.
     *
     * @param  list<ReglaAplicada>  $reglas
     */
    private function bonificacion(array $reglas, CompraData $compra): ?ReglaAplicada
    {
        $best = null;

        foreach ($reglas as $regla) {
            $condiciones = $regla->definicion['condiciones'] ?? null;

            if ($regla->tipo !== TipoRegla::Bonificacion || ! is_array($condiciones)) {
                continue;
            }

            $wins = $best === null || $this->multiplicador($regla) > $this->multiplicador($best);

            if ($wins && $this->condiciones->holdAll($condiciones, $compra)) {
                $best = $regla;
            }
        }

        return $best;
    }

    private function multiplicador(ReglaAplicada $bonificacion): int
    {
        $multiplicador = $bonificacion->definicion['multiplicador'] ?? 1;

        return is_int($multiplicador) && $multiplicador > 1 ? $multiplicador : 1;
    }

    /**
     * Points still allowed by each cap, in the order they apply. A cap left
     * empty does not limit.
     *
     * @param  array<array-key, mixed>  $definicion
     * @return array<string, int>
     */
    private function limits(array $definicion, int $puntosDelDia, int $puntosDelMes): array
    {
        $topes = is_array($definicion['topes'] ?? null) ? $definicion['topes'] : [];
        $alreadyEarned = [
            'puntos_por_transaccion' => 0,
            'puntos_por_dia' => $puntosDelDia,
            'puntos_por_mes' => $puntosDelMes,
        ];
        $limits = [];

        foreach (self::TOPES as $name) {
            $tope = $topes[$name] ?? null;

            if (is_int($tope)) {
                $limits[$name] = max(0, $tope - $alreadyEarned[$name]);
            }
        }

        return $limits;
    }
}
