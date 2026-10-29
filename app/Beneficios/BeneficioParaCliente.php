<?php

declare(strict_types=1);

namespace App\Beneficios;

use App\Enums\EstadoBeneficio;
use App\Enums\Nivel;
use App\Models\Beneficio;
use Carbon\CarbonImmutable;

/**
 * Un beneficio con su estado para un cliente en una fecha (E5-01, regla 9).
 * Solo trae los datos que corresponden a su estado:
 *
 * - disponible: `valor` y, si un nivel más alto da más, `siguiente`.
 * - bloqueado: `nivelRequerido` y `puntosFaltantes`.
 * - usado: `vuelve` (null de por vida).
 * - no disponible hoy: `proximaFecha` o `motivo` (`sin_fecha_nacimiento`).
 * - sin cupos: nada más.
 */
final class BeneficioParaCliente
{
    public const SIN_FECHA_NACIMIENTO = 'sin_fecha_nacimiento';

    public readonly Beneficio $beneficio;

    public readonly EstadoBeneficio $estado;

    public readonly ?int $valor;

    /** @var array{nivel: Nivel, valor: int, puntos_faltantes: int}|null */
    public readonly ?array $siguiente;

    public readonly ?Nivel $nivelRequerido;

    public readonly ?int $puntosFaltantes;

    public readonly ?CarbonImmutable $vuelve;

    public readonly ?CarbonImmutable $proximaFecha;

    public readonly ?string $motivo;

    /**
     * @param  array{nivel: Nivel, valor: int, puntos_faltantes: int}|null  $siguiente
     */
    private function __construct(
        Beneficio $beneficio,
        EstadoBeneficio $estado,
        ?int $valor = null,
        ?array $siguiente = null,
        ?Nivel $nivelRequerido = null,
        ?int $puntosFaltantes = null,
        ?CarbonImmutable $vuelve = null,
        ?CarbonImmutable $proximaFecha = null,
        ?string $motivo = null,
    ) {
        $this->beneficio = $beneficio;
        $this->estado = $estado;
        $this->valor = $valor;
        $this->siguiente = $siguiente;
        $this->nivelRequerido = $nivelRequerido;
        $this->puntosFaltantes = $puntosFaltantes;
        $this->vuelve = $vuelve;
        $this->proximaFecha = $proximaFecha;
        $this->motivo = $motivo;
    }

    /**
     * @param  array{nivel: Nivel, valor: int, puntos_faltantes: int}|null  $siguiente
     */
    public static function disponible(Beneficio $beneficio, int $valor, ?array $siguiente): self
    {
        return new self($beneficio, EstadoBeneficio::Disponible, valor: $valor, siguiente: $siguiente);
    }

    public static function bloqueado(Beneficio $beneficio, Nivel $nivelRequerido, int $puntosFaltantes): self
    {
        return new self(
            $beneficio,
            EstadoBeneficio::Bloqueado,
            nivelRequerido: $nivelRequerido,
            puntosFaltantes: $puntosFaltantes,
        );
    }

    public static function sinCupos(Beneficio $beneficio): self
    {
        return new self($beneficio, EstadoBeneficio::SinCupos);
    }

    public static function usado(Beneficio $beneficio, ?CarbonImmutable $vuelve): self
    {
        return new self($beneficio, EstadoBeneficio::Usado, vuelve: $vuelve);
    }

    public static function noDisponibleHoy(Beneficio $beneficio, ?CarbonImmutable $proximaFecha, ?string $motivo): self
    {
        return new self($beneficio, EstadoBeneficio::NoDisponibleHoy, proximaFecha: $proximaFecha, motivo: $motivo);
    }
}
