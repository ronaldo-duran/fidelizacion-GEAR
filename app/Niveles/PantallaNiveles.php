<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Beneficios\BeneficiosParaCliente;
use App\Enums\Nivel;
use App\Models\Cliente;
use App\Models\PlanMembresia;
use Carbon\CarbonImmutable;

/**
 * Los datos de la pantalla de niveles (E8-03, reglas 8 y 9): las cuatro
 * tarjetas y lo que explica la de Diamante. Cada número sale de su fuente:
 * umbrales de la regla `niveles`, duración del evento de
 * `config/niveles.php` y precio de `planes_membresia`.
 */
class PantallaNiveles
{
    private readonly NivelEfectivo $niveles;

    private readonly NivelCalculator $calculator;

    private readonly BeneficiosParaCliente $beneficios;

    public function __construct(NivelEfectivo $niveles, NivelCalculator $calculator, BeneficiosParaCliente $beneficios)
    {
        $this->niveles = $niveles;
        $this->calculator = $calculator;
        $this->beneficios = $beneficios;
    }

    /**
     * @return list<TarjetaNivel>
     */
    public function tarjetas(Cliente $cliente, ?CarbonImmutable $dia = null): array
    {
        $definicion = $this->niveles->definicionNiveles();
        $conteo = $this->beneficios->conteoPorNivel($dia);
        $siguiente = $this->calculator->siguiente($cliente->nivel_efectivo, $cliente->saldo_puntos, $definicion);
        $tarjetas = [];

        foreach (Nivel::cases() as $nivel) {
            $tarjetas[] = new TarjetaNivel(
                $nivel,
                $nivel === Nivel::Bronce ? null : $this->calculator->umbral($nivel, $definicion),
                $conteo[$nivel->value] ?? 0,
                $nivel === $cliente->nivel_efectivo,
                $siguiente !== null && $siguiente['nivel'] === $nivel ? $siguiente['faltan'] : null,
            );
        }

        return $tarjetas;
    }

    /**
     * Meses de Diamante que da la compra de un inmueble, o null si la
     * configuración no lo trae.
     */
    public function mesesPorInmueble(): ?int
    {
        $meses = config('niveles.event_rules.compra_inmueble.default_validity_months');

        return is_int($meses) ? $meses : null;
    }

    /**
     * El plan activo de menor duración (y, entre esos, el más barato): el
     * "desde" del botón de la suscripción.
     */
    public function planDesde(): ?PlanMembresia
    {
        $query = PlanMembresia::query();
        $query->getQuery()->where('activo', true)->orderBy('duracion_meses')->orderBy('precio_cop');

        return $query->first();
    }

    public function tieneMembresiaVigente(Cliente $cliente, ?CarbonImmutable $dia = null): bool
    {
        $dia = ($dia ?? CarbonImmutable::now())->startOfDay();

        return $cliente->nivel_por_membresia !== null && $cliente->membresia_vigente_hasta?->gte($dia) === true;
    }
}
